# Betriebshandbuch

Was ein Mensch wissen muss, der die Plattform ausrollt, am Laufen hält und nach einem
Ausfall wieder hochbringt (FB-044).

Dieses Dokument beschreibt den **Ist-Stand des Repositories**. Wo etwas noch nicht
eingerichtet ist, steht das ausdrücklich dabei — ein Handbuch, das eine Automatik
beschreibt, die es nicht gibt, ist schlimmer als keines.

---

## 1. Deployment

### Wo die Anwendung live läuft

| Was | Wert | woher bestätigt |
|---|---|---|
| Öffentliche Adresse | `https://leads.widimedia.com` | abgefragt am 2026-10-12, liefert das Portal (HTTP 200, `/portal` leitet auf `/login`) |
| Davor | Cloudflare (`server: cloudflare`, DNS zeigt auf 188.114.96.3/97.3) | gleiche Abfrage |
| Verzeichnis auf dem Server | `/home/leads/htdocs/leads.widimedia.com` | Betriebsnotiz; CloudPanel-Schnitt (`/home/<user>/htdocs/<domain>`), **keine** Deployer-Struktur mit `releases/` und `current/` |
| Systembenutzer | `leads` | dito |
| Ursprungsserver | `88.198.64.145` (Hetzner, `Ubuntu-2404-noble-amd64-base`) | geprüft am 2026-10-12: `curl --resolve leads.widimedia.com:443:88.198.64.145` liefert dieselbe Seite wie der Weg über Cloudflare |
| SSH-Zugang | `ssh leads` (Benutzer `root`, Schlüssel `~/.ssh/id_ed25519`) | Eintrag `Host leads` in `~/.ssh/config`, Ticket #66; dieselbe Maschine wie der dort schon vorhandene `Host sun` |
| PHP | `/usr/bin/php8.4`, identisch mit `/usr/bin/php` | der FPM-Pool der Site liegt unter `/etc/php/8.4/fpm/pool.d/leads.widimedia.com.conf` (Listen `127.0.0.1:19002`, User `leads`); 8.5 ist auf der Maschine installiert, bedient aber **andere** Sites |
| Queue | Redis, Horizon als **systemd**-Dienst `horizon-widileads` | `systemctl cat horizon-widileads`; Supervisor läuft auf der Maschine, kennt aber keinen Eintrag für widileads (Abschnitt 2) |
| Nachbarschaft | geteilte Maschine (minber, sanitaerfinden, kasernencheck u. a.) | `ls /etc/nginx/sites-enabled` — jeder Eingriff am Webserver, an PHP oder an Redis trifft fremde Projekte mit |

Über die DNS-Antwort ist der Server nicht zu finden — Cloudflare antwortet mit seinen
eigenen Adressen (188.114.96.3/97.3). Der Ursprung ist `88.198.64.145`; der Nachweis ist
die Abfrage mit erzwungener Auflösung oben in der Tabelle. In `~/.ssh/config` steht dafür
`Host leads` (Benutzer `root`). Alles unten Beschriebene beginnt mit:

```bash
ssh leads
cd /home/leads/htdocs/leads.widimedia.com
```

### Womit ausgerollt wird

**Nicht mit Deployer.** `deploy.php` liegt unverändert aus dem SaaSykit-Boilerplate im
Repository und trägt nur Platzhalter (`$host = '1.2.3.4'`,
`git@github.com:username/saasykit.git`, `$deployPath = '~/app'`). Damit niemand
versehentlich dagegen deployt, bricht die Datei beim Aufruf mit einem Hinweis ab,
solange die Platzhalter darin stehen.

Ein Umstellen auf Deployer wäre kein Konfigurationsschritt, sondern ein Umbau: Deployer
erwartet unter `$deployPath` seine eigene Struktur (`releases/`, `shared/`, Symlink
`current`), das Live-Verzeichnis ist aber ein flaches CloudPanel-Verzeichnis. Diese
Entscheidung gehört dem Betreiber, nicht einem Rollout.

**So läuft es: `git pull` im Live-Verzeichnis.** Das Verzeichnis ist ein gewöhnlicher
Git-Arbeitsbaum auf `main` mit `origin = git@github.com:enesk/widileads.git`; das
Reflog zeigt ausschließlich `pull: Fast-forward` (geprüft am 2026-10-12, zuletzt am
2026-10-11 auf `59e8802a`). Kein Rsync, kein Deployer, keine Releases.

Zwei Eigenheiten, die man kennen muss:

- **Der Pull läuft als `root`.** Der Benutzer `leads` hat keinen GitHub-Schlüssel
  (`/home/leads/.ssh/authorized_keys` und `config` sind leer), `sudo -u leads git fetch`
  endet mit „Please make sure you have the correct access rights". Root authentifiziert
  über `/root/.ssh/config` → `github_deploy`. Deshalb gehört hinter jeden Pull ein
  `chown`, sonst liegen frische Dateien root — der Webserver und Horizon laufen als
  `leads`.
- **Gebaute Assets liegen nicht im Repository.** `/public/build` steht in `.gitignore`
  und wird auf dem Server gebaut (`node_modules` liegt dort). Ein Pull ohne `npm run
  build` lässt die Anwendung mit dem alten Bundle laufen.

Reihenfolge für einen Rollout, im Live-Verzeichnis:

```bash
# 1. Code holen (als root, siehe oben)
git pull --ff-only
chown -R leads:leads /home/leads/htdocs/leads.widimedia.com

# 2. Abhaengigkeiten, nur wenn composer.lock / package-lock.json sich geaendert haben
sudo -u leads composer install --no-dev --optimize-autoloader
sudo -u leads npm ci

# 3. Assets - immer, sobald sich etwas unter resources/ bewegt hat
sudo -u leads npm run build

# 4. Datenbank
sudo -u leads /usr/bin/php8.4 artisan migrate --force

# 5. Caches erneuern. bootstrap/cache/config.php UND routes-v7.php sind auf diesem
#    Server gefuellt - ohne diesen Schritt wirkt weder neue Konfiguration noch eine
#    neue Route.
sudo -u leads /usr/bin/php8.4 artisan optimize:clear

# 5a. Livewire-Skript als echte Datei ablegen. Auf diesem Server erreicht keine
#     Adresse, die auf .js endet, jemals PHP - nginx antwortet selbst mit 404 und
#     Cloudflare speichert die 404 vier Stunden. Livewire liefert sein Skript
#     sonst ueber eine dynamische Route; ohne diesen Schritt bleibt das Filament-
#     Panel leer. Gehoert in jedes Release, auch wenn public/vendor/livewire
#     schon existiert.
sudo -u leads /usr/bin/php8.4 artisan livewire:publish --assets

sudo -u leads /usr/bin/php8.4 artisan optimize

# 6. Worker neu starten, sonst verarbeitet er Jobs mit dem alten Code
systemctl restart horizon-widileads

# 7. Nacharbeiten
sudo -u leads /usr/bin/php8.4 artisan app:generate-sitemap
sudo -u leads /usr/bin/php8.4 artisan app:export-configs
```

**Immer `/usr/bin/php8.4`, nicht 8.5.** Der FPM-Pool der Site läuft auf 8.4; ein mit 8.5
geschriebener Konfigurations-Cache wird von 8.4 wieder gelesen, aber jede Prüfung per
`tinker` zeigt sonst eine andere Laufzeit als die, die die Seite ausliefert.

Gegenprobe nach dem Rollout:

```bash
git log --oneline -1                         # steht der erwartete Commit?
git status --short                           # leer; .env.bak-* und public/vendor/livewire sind ignoriert
systemctl show -p ActiveEnterTimestamp horizon-widileads   # jung, nicht Wochen alt
stat -c '%y' public/build/manifest.json      # juenger als der Commit
curl -sI https://leads.widimedia.com/ | head -1
```

Soll nachgewiesen werden, dass ein bestimmtes Stück Frontend-Code wirklich im
gebauten Bundle steckt, taugt der Name einer lokalen Funktion dafür nicht — esbuild
benennt sie um. Belastbar sind nur Zeichenketten und globale Namen, etwa
`grep -rl OTPCredential public/build/assets` für den WebOTP-Pfad der Code-Kästchen.

Der Cron braucht genau einen Eintrag:

```
* * * * * cd /home/leads/htdocs/leads.widimedia.com && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1
```

Er steht in der Crontab des Benutzers **`leads`**, nicht bei `root` — Webserver und
Horizon laufen als `leads`, ein Lauf als `root` hinterließe Cache- und Logdateien, die
die Anwendung danach nicht mehr schreiben kann. Angelegt am 2026-10-12 (Ticket #67),
nachgewiesen über `grep CRON /var/log/syslog | grep '(leads)'`. Bearbeitet wird er als
`root` mit `crontab -u leads -e`, aufgelistet mit `crontab -u leads -l`.

### Umgebungsvariablen

Vorlage ist `.env.example`. Für den Betrieb gilt zusätzlich:

| Variable | Wert im Betrieb | Warum |
|---|---|---|
| `APP_ENV` | `production` | steuert Fehlerausgabe und den Schutz destruktiver Befehle |
| `APP_DEBUG` | `false` | sonst steht der Stacktrace im Browser |
| `APP_URL` | `https://leads.widimedia.com` | geht in Links, Mails und in den WebOTP-Host der SMS |
| `APP_LOCALE` | `de` | die Oberfläche ist deutsch |
| `QUEUE_CONNECTION` | `redis` | `.env.example` steht auf `sync`; damit liefe jeder Job im Request |
| `CACHE_DRIVER` | `redis` | |
| `SESSION_DRIVER` | `redis` oder `database` | `file` skaliert nicht über mehrere Prozesse |
| `TRUSTED_PROXIES` | Cloudflare-Netze oder `*` | hinter Cloudflare sieht Laravel sonst `http` — dann scheitert jede Twilio-Signaturprüfung (FB-084) |
| `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_FROM` | aus der Twilio-Konsole, `TWILIO_FROM` in E.164 | Anrufe und SMS |
| `TWILIO_VERIFY_SID` | `VA…` des Verify-Service | ohne sie bricht jede echte Rufnummernbestätigung ab; kommt **nur** aus der `.env`, die Admin-Seite pflegt sie nicht (Ticket #63) |
| `LEAD_CALLS_ENFORCE_FROM` | Datum, ab dem Käufer über das Portal anrufen konnten — oder leer | Cutover der Erreichbarkeitsprüfung. **Leer heißt: Regelwerk aus**, `leads:resolve-expired` und `leads:send-deadline-reminders` finden nichts (Ticket #73) |
| `FUNNEL_ALLOW_DESTRUCTIVE` | **nicht gesetzt** | siehe unten |

Die Feature-Flags der mitgelieferten SaaSykit-Module (`FUNNEL_FEATURE_BLOG_ENABLED`,
`…_ROADMAP_…`, `…_ANNOUNCEMENTS_…`, `…_REFERRAL_…`) stehen ohne Angabe auf `false`. Das
ist der gewünschte Zustand — die Module bleiben im Code, aber unsichtbar.

> **`FUNNEL_ALLOW_DESTRUCTIVE` gehört niemals in eine `.env` außerhalb der
> Testumgebung.** Der `DestructiveCommandGuard` aus FB-003 blockt `migrate:fresh`,
> `migrate:refresh`, `migrate:reset` und `db:wipe`. Er greift nur, solange die Variable
> nicht auf einen wahren Wert steht **und** `APP_ENV` nicht `testing` ist. Wer sie im
> Betrieb setzt, entfernt den einzigen Schutz davor, die Produktivdatenbank mit einem
> Tippfehler zu leeren.

#### Abgleich mit der Live-`.env` (Stand 2026-10-12, Ticket #66)

| Variable | live | Urteil |
|---|---|---|
| `APP_ENV` | `production` | passt |
| `APP_DEBUG` | `false` | passt |
| `APP_URL` | `https://leads.widimedia.com` | passt |
| `TRUSTED_PROXIES` | `*` | passt — damit liest Laravel `X-Forwarded-Proto` von Cloudflare, die Twilio-Signaturprüfung rechnet mit `https` (FB-084) |
| `QUEUE_CONNECTION` | `redis` | passt |
| `FUNNEL_ALLOW_DESTRUCTIVE` | nicht gesetzt | passt |
| `APP_LOCALE` | nicht gesetzt | unkritisch, `config/app.php` fällt auf `de` zurück |
| `CACHE_DRIVER` | `file` | abweichend, aber wirksam — `config/cache.php` liest in diesem Projekt `CACHE_DRIVER` (nicht `CACHE_STORE`). Auf einer Maschine mit einem FPM-Pool tragbar |
| `SESSION_DRIVER` | `file` | abweichend, bewusst gelassen: ein Umstellen wirft alle angemeldeten Käufer heraus. Erst anfassen, wenn ein zweiter Prozess dazukommt |
| `TWILIO_FROM` | `"+4915115509444"` | am 2026-10-12 nachgetragen (Ticket #69), siehe unten |
| `TWILIO_SID`, `TWILIO_TOKEN` | **leer** (`""`) in der `.env`, in der Admin-Seite „Twilio" gesetzt (Konfigzeilen `services.twilio.sid/.token`, verschlüsselt in `configs`) | bewusst so gelassen: `TwilioOutboundCallClient`, `TwilioVerifyClient`, `VerifyTwilioSignature` und `leads:check-call-setup` lesen beide Orte, und ein Auth Token im Klartext in der `.env` bringt keinen Vorteil. Nur die Container-Bindung `Twilio\Rest\Client` in `AppServiceProvider` liest ausschliesslich `config('twilio.*')` — die wird von keinem Dienst aufgelöst (geprüft am 2026-10-12) |
| `TWILIO_VERIFY_SID` | `VA8e6c9468220ed2021adbdb7f7ca5f7d0` | am 2026-10-12 eingetragen (Ticket #63), siehe unten |
| `PHONE_VERIFICATION_FAKE`, `TWILIO_VERIFY_WEBOTP` | beide ausdrücklich `false` | am 2026-10-12 mit der Verify-SID zusammen gesetzt (Ticket #63) |
| `LEAD_CALLS_ENFORCE_FROM` | `""` (leer) | am 2026-10-12 ausdrücklich leer eingetragen (Ticket #73), siehe unten — das Regelwerk ist damit aus; bleibt leer, bis `leads:cutover-status` ohne FEHLER laeuft (Ticket #94: check-call-setup --production ohne FEHLER, mindestens eine Zeile in `call_attempts`, mindestens ein Konto mit bestaetigter Rufnummer) — am 2026-10-12 dreimal gemessen (Tickets #76, #85): nichts davon erfuellt, und live hat **kein einziges Konto eine bestaetigte Rufnummer** — damit kann niemand das Anrufpanel ueberhaupt oeffnen |

### Verify-SID auf dem Live-Server (Ticket #63, erledigt am 2026-10-12)

Der Verify-Service ist am 2026-10-12 im Twilio-Konto angelegt (Friendly Name
`widileads`, SID `VA8e6c9468220ed2021adbdb7f7ca5f7d0`, `code_length: 6`, Code-Laufzeit
10 Minuten — Twilio-Vorgabe). Die Live-`.env` trägt den Wert seit demselben Tag; direkt
unter `TWILIO_FROM` stehen dort jetzt:

```
TWILIO_VERIFY_SID="VA8e6c9468220ed2021adbdb7f7ca5f7d0"
# WebOTP bleibt aus, bis das Custom-Template freigegeben ist (Tickets #60/#65).
TWILIO_VERIFY_WEBOTP=false
PHONE_VERIFICATION_FAKE=false
```

Die Sicherung der vorigen Fassung liegt als `.env.bak-20261012-63` im Live-Verzeichnis.

Nach jeder Änderung an diesen Werten, als Benutzer `leads` im Live-Verzeichnis:

```bash
# 1. Konfigurationscache erneuern, sonst wirkt der Wert nicht.
#    Dieser Server faehrt MIT Cache (bootstrap/cache/config.php ist gefuellt),
#    deshalb beide Schritte:
sudo -u leads /usr/bin/php8.4 artisan config:clear
sudo -u leads /usr/bin/php8.4 artisan config:cache

# 2. Gegenprobe - erwartet dieselbe SID
sudo -u leads /usr/bin/php8.4 artisan tinker --execute 'echo config("services.twilio.verify_sid");'

# 3. Der Worker haelt die Konfiguration im Speicher
systemctl restart horizon-widileads
```

> **Die Gegenprobe über `config()` liefert auf diesem Server derzeit noch nichts** —
> nicht weil die `.env` falsch stünde, sondern weil der ausgerollte Stand (`59e8802a`)
> den Verify-Zweig noch nicht enthält: `config/services.php` kennt dort den Schlüssel
> `twilio.verify_sid` gar nicht, `app/Services/Twilio/TwilioVerifyClient.php` liegt noch
> nicht auf dem Server. Belegbar ist der Wert deshalb nur über die Umgebung selbst:
>
> ```bash
> sudo -u leads /usr/bin/php8.4 artisan config:clear
> sudo -u leads /usr/bin/php8.4 artisan tinker --execute 'echo env("TWILIO_VERIFY_SID");'
> sudo -u leads /usr/bin/php8.4 artisan config:cache
> ```
>
> Das liefert die SID (geprüft am 2026-10-12). Achtung: **mit** gefülltem
> Konfigurationscache überspringt Laravel das Laden der `.env` komplett, dann gibt `env()`
> immer `null` zurück — deshalb der Umweg über `config:clear`. Sobald der Verify-Code
> ausgerollt ist, zieht `config('services.twilio.verify_sid')` den Wert ohne weiteres
> Zutun.

Zwei Werte in derselben `.env` gehören dazu:

| Variable | Wert | Warum |
|---|---|---|
| `PHONE_VERIFICATION_FAKE` | `false` | in `production` wird die Attrappe ohnehin ignoriert, der Wert gehört trotzdem auf `false` |
| `TWILIO_VERIFY_WEBOTP` | `false`, bis das Custom-Template freigegeben ist | Twilio lehnt eine Substitution ab, die das Template nicht führt — dann scheitert **jeder** SMS-Versand, nicht nur WebOTP (Tickets #60/#65) |

Die Admin-Seite „Twilio" pflegt `TWILIO_VERIFY_SID` **nicht** (dort stehen nur `sid`,
`token`, `from`); `config('services.twilio.verify_sid')` liest ausschliesslich die
Umgebung.

### Portal-Nummer `TWILIO_FROM` auf dem Live-Server (Ticket #69, erledigt am 2026-10-12)

Die Live-`.env` trägt seit dem 2026-10-12 `TWILIO_FROM="+4915115509444"` (Zeile 113,
vorher `""`), danach `config:clear`/`config:cache` und ein Neustart von
`horizon-widileads`. Gegenprobe:

```bash
sudo -u leads /usr/bin/php8.4 artisan tinker --execute 'echo config("twilio.from_number");'
sudo -u leads /usr/bin/php8.4 artisan leads:check-call-setup --production
```

Beides liefert `+4915115509444`.

**Der geführte Anruf war auch vorher nicht blind.** `CallService::platformNumber()`
(`app/Services/CallService.php:171-176`) liest zuerst `config('twilio.from_number')`
(nur `.env`) und fällt auf `config('services.twilio.from')` zurück — und dieser
Schlüssel steht in `ConfigConstants::OVERRIDABLE_CONFIGS`, wird also von
`ConfigService::loadConfigs()` bei jedem Boot aus Cache/Datenbank über die Umgebung
gelegt. Die Nummer kam deshalb aus der Admin-Seite „Twilio". Der Eintrag in der `.env`
nimmt dem Anruf die Abhängigkeit von Redis-Cache, `configs`-Tabelle und dem Schalter
`app.admin_settings.enabled`.

> **Vorrangregel, die daraus folgt:** `.env` schlägt die Admin-Seite. Wird die
> Portal-Nummer künftig im Admin-Panel geändert, muss `TWILIO_FROM` mitgeändert werden —
> sonst wählt Twilio weiter mit dem alten Wert, während die Oberfläche den neuen zeigt.
> `leads:check-call-setup` zeigt immer den tatsächlich gewählten Wert.

**Was offen bleibt (nicht Teil von #69):** Im Twilio-Konto ist **keine** Rufnummer
gekauft (`IncomingPhoneNumbers` ist leer, geprüft am 2026-10-12). `+4915115509444` ist
eine bei Twilio *verifizierte* Absendernummer (`OutgoingCallerIds`, Konto `Full`,
`active`). Als `From` eines ausgehenden Anrufs akzeptiert Twilio das — der geführte
Anruf ist also fahrbar. Nicht fahrbar sind Rückrufe auf die Portal-Nummer: sie landen
auf dem privaten Anschluss des Betreibers, nicht im Portal. Dazu kommt, dass das
deutsche Regulatory Bundle im Stand `twilio-rejected` steht und so lange kein
Nummernkauf möglich ist. `leads:check-call-setup --production` meldet beides als FEHLER.

### DE-Regulatory-Bundle: wer was ablehnt (Ticket #75, gemessen am 2026-10-12)

Ohne freigegebenes Bundle lehnt Twilio den Kauf einer deutschen Festnetznummer mit
Fehler 21649 ab. Das Bundle des Kontos steht auf `twilio-rejected` — **aber nicht als
Ganzes**. Lesend am Konto gemessen (`/v2/RegulatoryCompliance/...`, Zugangsdaten aus der
Admin-Seite „Twilio"):

| Gegenstand | SID | Stand |
|---|---|---|
| Bundle „Germany: Local - Business  2026-09-10 at 22:26:56" | `BU453802ae1b3873795765c92331067987` | `twilio-rejected` |
| Enduser (business), alle sieben Felder gesetzt | `IT7f0189cb8ec30714f468c8beb03506bf` | ohne Beanstandung |
| Nachweis `trade_license` (Identität + Anschrift, Adresse `AD85b419661bbfa2727843b36dd6759255`) | `RD62cc98332ef69d20c6dce9a993f37e94` | **`approved`** |
| Nachweis `tax_document` (Proof of Registration, Attribut `business_registration_number: 39272/47034`) | `RDb32d5306c281183a48a5df91da7ed340` | **`rejected`**, Fehler 18001 |

Fehlertext zum abgelehnten Dokument, wörtlich von Twilio:

> The document you have uploaded does not match our Regulatory Requirements criteria.
> Please upload a Supporting Document according to our Guidelines.

Alle fünf `Evaluations` des Bundles stehen auf `compliant` — die automatische Prüfung der
Felder ist also bestanden, abgelehnt hat die **manuelle** Prüfung, und zwar **genau ein
PDF**. Der Grund steht nicht in den Evaluations, sondern am Dokument
(`failure_reason`/`errors` der `SupportingDocuments`-Ressource); die Konsole zeigt
dasselbe.

**Folge für die Menschen-Sitzung:** Es ist kein neues Bundle zu bauen und auch die
Firmendaten sind nicht das Problem. Gebraucht wird **ein** neues PDF als Proof of
Registration — ein Handelsregisterauszug (Typ `commercial_registrar_excerpt`, deckt laut
Regulierung alle drei Nachweise ab) oder ein Finanzamtsschreiben, das Twilios Guidelines
genügt. Firmenname und Anschrift darauf müssen zu dem passen, was bereits freigegeben ist
(`widimedia`, Adresse `AD85b4…`, Platanenstr. 12, 76437 Rastatt) — nicht zur zweiten,
älteren Kontoadresse `AD68047f…` (Rheinauer Ring 66/3).

Der Weg bei Twilio für ein abgelehntes Bundle ist die **Bundle-Kopie**
(`POST /v2/RegulatoryCompliance/Bundles/BU453802…/Copies`): sie erzeugt einen `draft` mit
den bereits freigegebenen Teilen, darin wird nur das beanstandete Dokument ersetzt, dann
wieder `pending-review`. In der Konsole heißt das „Edit and resubmit".

> Genau das macht `leads:submit-de-bundle` seit Ticket #79 selbst. Rein lesend
> aufgerufen benennt es das beanstandete Teil (Tabelle `Gegenstand | SID | Stand | Grund`
> aus `failure_reason`/`errors`) und gibt den kopierbaren Aufruf mit genau der Option aus,
> die zum abgelehnten Nachweis gehört:
>
> ```bash
> php artisan leads:submit-de-bundle
> php artisan leads:submit-de-bundle --resubmit --proof-registration=/pfad/auszug.pdf --submit
> ```
>
> `--resubmit` legt die Bundle-Kopie an, hängt nur das abgelehnte Dokument ab, lädt das
> Ersatz-PDF hoch, hängt es an und setzt mit `--submit` auf `pending-review`. Enduser und
> freigegebene Nachweise werden **nicht** neu hochgeladen, das alte Bundle bleibt
> unangetastet. Fehlt eine Vorbedingung (kein eindeutiges abgelehntes Bundle, kein
> abgelehntes Dokument, PDF fehlt oder ist unlesbar), entsteht nichts im Konto — es kommt
> die Tabelle `Fehlt | Woher` und Exit 1. Ein `--submit` **ohne** `--resubmit` legt in
> diesem Zustand bewusst nichts an und endet mit Exit 1. Bricht ein Lauf nach dem Anlegen
> der Kopie ab, nennt die Fehlermeldung die Kopie-SID; der nächste Lauf mit
> `--resubmit --bundle=BU…` verwendet dieselbe Kopie weiter und legt keine zweite an.

Nach der Freigabe (`status: twilio-approved`) ist die Reihenfolge: DE-**Voice**-Nummer
kaufen, Nummer in der Admin-Seite „Twilio" **und** in `TWILIO_FROM` der Live-`.env`
eintragen (die `.env` schlägt die Admin-Seite, siehe oben), `TWILIO_FROM_IS_OWNED=true`
setzen, `config:clear`/`config:cache`, `systemctl restart horizon-widileads`, und zum
Schluss muss

```bash
sudo -u leads /usr/bin/php8.4 artisan leads:check-call-setup --production
```

ohne FEHLER durchlaufen. Erst dann wird der Cutover im nächsten Abschnitt scharf
gestellt.

#### Was am Konto gemessen ist (Ticket #75, 2026-10-12)

Der Nummernkauf wurde **ausprobiert**, nicht geschätzt — dreimal `POST
/2010-04-01/Accounts/AC…/IncomingPhoneNumbers.json`, jedes Mal abgelehnt, also keine
Nummer gekauft und kein Geld bewegt:

| Versuch | Antwort von Twilio |
|---|---|
| Mobilnummer `+49 1588…` mit `AddressSid=AD85b419…` | `21649` — „Bundle required and not provided for country: [DE] and numberType: **[MOBILE]**" |
| Festnetznummer in einem fremden Ortsnetz (Pulheim) | `21615` — „No valid address created for: Pulheim" |
| Festnetznummer **in Rastatt** (`+49 7222 8079…`) mit derselben Adresse | `21649` — „Bundle required … numberType: **[LOCAL]**" |

Daraus folgt dreierlei für die Menschen-Sitzung:

* Das Bundle ist die **harte** Sperre. Es gibt keinen Weg daran vorbei — auch nicht über
  eine Mobilnummer: die Regulierung „Germany: Mobile - Business"
  (`RN0ffdc59bf88e48f2efdf8c440255723b`) verlangt **dieselben** sieben Enduser-Felder und
  **dieselben** drei Nachweise wie „Local - Business". Ein zweites Bundle für `mobile`
  aufzusetzen bringt also nichts, solange dasselbe PDF fehlt.
* Eine Festnetznummer **im eigenen Ortsnetz Rastatt ist im Bestand** — der Kauf scheitert
  derzeit nur am Bundle, nicht an der Verfügbarkeit. Zu finden sind diese Nummern aber nur
  über `Contains`, **nicht** über `AreaCode` (`AreaCode=7222` liefert für DE eine leere
  Liste, `Contains=+497222*` fünf Nummern):

  ```bash
  curl -s -u "$TWILIO_SID:$TWILIO_TOKEN" \
    "https://api.twilio.com/2010-04-01/Accounts/$TWILIO_SID/AvailablePhoneNumbers/DE/Local.json?Contains=%2B497222*&VoiceEnabled=true"
  ```

  Die Adresse muss zum Ortsnetz der Nummer passen (daher `21615` bei Pulheim): zu einer
  Rastatter Nummer gehört `AD85b419661bbfa2727843b36dd6759255`.
* Das Guthaben reicht: **18,88 USD** (`Balance.json`), eine deutsche Festnetznummer kostet
  gut 1 USD im Monat. Für den Kauf ist also nichts aufzuladen.

Beim Kauf nach der Freigabe sind **beide** Parameter mitzugeben — `BundleSid` des
freigegebenen Bundles und `AddressSid` der Rastatter Adresse; in der Konsole fragt die
Maske „Buy a number" dasselbe ab.

Zu den Nachweisen, gemessen an der Regulierung: Proof of Registration akzeptiert nur
`commercial_registrar_excerpt` oder `tax_document` — der bereits freigegebene
`trade_license` (Gewerbeanmeldung) deckt diesen Nachweis **nicht** ab, nur Identität und
Anschrift. Gebraucht wird also ein Handelsregisterauszug oder ein Dokument der
Steuerbehörde (Mitteilung des Finanzamts über die Steuernummer, USt-IdNr.-Bestätigung des
BZSt), ausgestellt auf `widimedia`, Platanenstr. 12, 76437 Rastatt.

> **Wartet auf den Betreiber.** Dieser Punkt ist von keinem Agenten weiter zu bringen: er
> braucht ein Dokument aus der Realität, Twilios manuelle Prüfung (Tage) und danach rund
> zehn Minuten Konsole plus `.env`. Alles Prüfbare daran ist gemessen und steht oben.


### Cutover der Erreichbarkeitsprüfung (Ticket #73, Stand 2026-10-12)

`LEAD_CALLS_ENFORCE_FROM` entscheidet, **ab welchem `delivered_at` ein Lead dem
Anruf-Regelwerk unterliegt**. Nur erfasste Leads schließt `leads:resolve-expired` nach
Fristablauf als `billable` ab — und genau das bucht beim Käufer ab.

Seit Ticket #73 gilt: **ohne Wert ist das Regelwerk aus.** Vorher bedeutete ein fehlender
Eintrag das Gegenteil (alle Leads erfasst); eine fehlende Zeile in der `.env` hätte den
gesamten Bestand abgerechnet, obwohl dessen Käufer das Anrufpanel nie hatten.

Die Live-`.env` trägt deshalb vorläufig

```
# Cutover der Erreichbarkeitspruefung: leer = Regelwerk aus (Ticket #73).
# Eintragen, sobald Kaeufer ueber das Portal tatsaechlich anrufen koennen.
LEAD_CALLS_ENFORCE_FROM=""
```

Scharf gestellt wird erst, wenn Portal-Anrufe live funktionieren — dazu fehlt noch eine
gekaufte Twilio-Rufnummer (Ticket #72/#75). Dann hier das Datum des ersten Tages
eintragen, an dem das Anrufpanel erreichbar war, und `optimize:clear` + `optimize`
hinterher.

#### Woran der Cutover hängt (Ticket #76, gemessen am 2026-10-12)

| Prüfung | Stand am 2026-10-12 |
|---|---|
| `leads:check-call-setup --production` | FEHLER bei `Portal-Nummer gekauft` und `Regulatory Bundle DE (local)` |
| `TWILIO_FROM` live | `+4915115509444` — verifizierte Privatnummer, nicht gekauft |
| `call_attempts` live | **0 Zeilen** — über das Portal wurde live noch nie gewählt |
| bestätigte Rufnummern live | **0 von 4 Konten** — `users.phone_number_verified_at` ist überall `null` (gemessen am 2026-10-12, Ticket #85) |

Die dritte Zeile ist das eigentliche Kriterium: Solange es keinen einzigen Anrufversuch
gibt, gibt es auch keinen „ersten Tag, an dem das Anrufpanel erreichbar war", und jedes
Datum wäre geraten. **Deshalb bleibt der Wert leer, solange eine der drei messbaren
Vorbedingungen fehlt: `leads:check-call-setup --production` ohne FEHLER, mindestens eine
Zeile in `call_attempts`, mindestens ein Konto mit bestätigter Rufnummer.** Alle drei
zusammen misst `leads:cutover-status` (Ticket #94, siehe „Reihenfolge beim
Scharfstellen"). Der Ticketstand von #75 ist dafür kein
Ersatz — #75 ist geschlossen, sobald der Agentenanteil gemessen und dokumentiert ist,
die gekaufte Nummer kommt erst mit der Betreiber-Sitzung.

#### Was den ersten Anrufversuch technisch nicht aufhält (Ticket #82, am Code gelesen am 2026-10-12)

Die gekaufte Nummer ist Voraussetzung dafür, dass `leads:check-call-setup --production`
ohne FEHLER läuft — aber **nicht** dafür, dass überhaupt gewählt werden kann.
`CallService::fromNumber()` (`app/Services/CallService.php:167`) nimmt die Portal-Nummer
nur aus `twilio.from_number`, ersatzweise `services.twilio.from`, und prüft an keiner
Stelle, ob sie gekauft oder bloß verifiziert ist; `twilio.from_number_is_owned` wertet
ausschließlich die Kachel „Probleme?" aus
(`app/Livewire/Portal/CallerIdVerification.php:307`, Ticket #74). Twilio lässt ausgehende
Anrufe von einer verifizierten Absendernummer auf einem Full-Konto zu. Ein echter
Portal-Anruf — und damit die erste Zeile in `call_attempts` — ist deshalb schon heute
machbar: Absender `+4915115509444`, gewählt über das Anrufpanel eines Käufers mit
bestätigter Rufnummer, Ziel ein Lead, dessen Telefonnummer dem Betreiber selbst gehört
(zweites Telefon) — damit ruft der Probeanruf niemand Fremden an.

**Was ihn doch aufhält — am 2026-10-12 live gemessen (Ticket #85).** Der Satz oben setzt
„einen Käufer mit bestätigter Rufnummer" voraus, und genau den gibt es live nicht:
`users.phone_number_verified_at` ist bei allen vier Konten `null`
(`hello@eneskul.com`, `kul@widimedia.com`, `tolga.altuntas@allianz.de`,
`engin.keskinsoy@allianz.de`). `EnsurePhoneVerified` sperrt damit jeden angemeldeten
Screen; das Anrufpanel ist für niemanden erreichbar. Vor dem ersten Anrufversuch steht
deshalb **die Rufnummernbestätigung des Betreibers am eigenen Telefon** — die läuft
unabhängig vom Nummernkauf: `TWILIO_VERIFY_SID` ist gesetzt, `PHONE_VERIFICATION_FAKE` ist
`false` und `TwilioVerifyClient` nimmt die Zugangsdaten aus der Admin-Seite „Twilio"
(`services.twilio.sid/.token`), nicht aus den leeren `.env`-Zeilen. Reihenfolge der
Betreiber-Sitzung also: **1.** eigene Nummer im Portal bestätigen, **2.** Übungs-Lead mit
`leads:call-probe` und einer **zweiten** eigenen Nummer anlegen (dieselbe Nummer wie
`TWILIO_FROM` geht nicht, Twilio lehnt `From == To` ab, Ticket #93), **3.** anrufen,
**4.** Cutover setzen.

**Frühester zulässiger Cutover (Stand 2026-10-12).** Die fünf offenen Reservierungen
77–81 wurden am 2026-10-11 zwischen 17:24:44 und 23:15:33 ausgeliefert (Leads
1791/1795/1798/1801/1803, Käufer Mandant 2 „Schoder & Altuntas OHG" — ein echter Kunde,
Kaufbeleg 81 steht auf dessen Nutzer 9 —, je 15,00 EUR, Frist 2026-10-18). Ein Cutover
auf `2026-10-11` oder früher rechnet sie ab, obwohl zu ihnen kein Anrufversuch existiert
und der Käufer durch die Telefonsperre nie anrufen konnte. **Zulässig ist deshalb nur ein
Datum ab `2026-10-12`** — und sinnvoll ist das Datum des ersten echten Anrufversuchs aus
Punkt 2 der Reihenfolge unten. Ein geratenes Datum in der Zukunft ist keine Alternative:
es stellt das Regelwerk unbeobachtet scharf, während leer „aus" bedeutet und geprüft wird.

Am Kriterium oben ändert das **nichts**: scharf gestellt wird erst, wenn *beide*
Bedingungen erfüllt sind. Es heißt nur, dass die zweite Bedingung nicht auf Twilios
Prüfzeit wartet und in der Betreiber-Sitzung **vorweg** erledigt werden kann; die
Reihenfolge „erst Bundle, dann Anruf" aus Ticket #82 ist keine Zwangsreihenfolge.

#### Reihenfolge beim Scharfstellen

Den Stand aller Vorbedingungen misst **ein Befehl** (Ticket #94) — er ersetzt die beiden
`tinker`-Blocks, die hier früher standen:

```bash
sudo -u leads /usr/bin/php8.4 artisan leads:cutover-status
```

Er zeigt in einer Tabelle:

1. **Einrichtung des Anrufwegs** — er ruft `leads:check-call-setup --production`
   selbst auf (stumm) und übernimmt dessen Urteil; Einzelbefunde liefert ein eigener
   Aufruf von `leads:check-call-setup --production`. Mit `--skip-setup-check` bleibt die
   Twilio-Abfrage aus.
2. **Erster echter Anrufversuch** — Zeilen in `call_attempts` samt `min(created_at)`
   als Vorschlag für das Cutover-Datum.
3. **Konto mit bestätigter Rufnummer** — ohne ein Konto mit
   `users.phone_number_verified_at` sperrt `EnsurePhoneVerified` jeden angemeldeten
   Screen, und das Anrufpanel ist für niemanden erreichbar (Ticket #85: live 0 von 4).
4. **Frühestes zulässiges Datum** — ein Tag nach der letzten Auslieferung eines Leads
   mit offener Reservierung. Ein Cutover darunter rechnet Leads ab, zu denen kein
   Anrufversuch existiert.
5. **Trockenlauf** — was `leads:resolve-expired` mit diesem Cutover greifen würde:
   Leads, Kaufbelege in Reservierung und Geldsumme. Das Datum kommt aus `--date=`,
   ersatzweise aus dem gesetzten `LEAD_CALLS_ENFORCE_FROM`, ersatzweise aus Punkt 2.
   Mit `--at=` wird gegen einen anderen Stichtag gerechnet — so beantwortet man „was
   greift der erste Lauf **nach** Fristablauf?", statt nur den heutigen Stand zu sehen.

Der Trockenlauf rechnet gegen `ResolveExpiredLeads::expiredLeadsQuery()` selbst
(`app/Console/Commands/ResolveExpiredLeads.php`) — dieselbe Methode, die der
03:00-Lauf benutzt. Die Bedingung steht damit an **einer** Stelle: ohne
Mandanten-Scopes, `contact_status = open`, `lead_state` nicht in
`LeadResolver::undecidableStates()`, `deadline_at <= Stichtag`,
`delivered_at >= enforce_from`; leerer Cutover ⇒ `1 = 0`. Hier ist nichts mehr
nachzuziehen, wenn sich die Bedingung ändert.

`erreicht` steht bewusst **nicht** in dieser Ausschlussmenge: Diesen Zustand setzt
`app:settle-elapsed-complaint-periods` um 01:00 nach Ablauf der Reklamationsfrist,
abgebucht wird der Kaufpreis aber erst durch das `LeadResolved` des 03:00-Laufs. Würde
`erreicht` mit ausgeklammert, bliebe die Reservierung für immer stehen und
`wallet:verify` liefe auf eine Differenz.

Die Sitzung selbst:

1. `leads:cutover-status` läuft ohne FEHLER (Vorbedingungen 1 bis 3).
2. Datum wählen: der Vorschlag aus Punkt 2, nie früher als Punkt 4.
3. Trockenlauf mit genau diesem Datum ansehen:
   `leads:cutover-status --date=<Datum> --at=<Datum des ersten Laufs nach Fristablauf>`.
   Jeder Treffer wird nachts als `billable`/`deadline` abgerechnet. Kommt etwas
   Unerwartetes vor, erst den Cutover später legen, dann abrechnen — nie umgekehrt.
4. `LEAD_CALLS_ENFORCE_FROM="<Datum>"` in die Live-`.env`, dann `optimize:clear` +
   `optimize` und `systemctl restart horizon-widileads`. Danach denselben Befehl noch
   einmal ohne `--date` — er rechnet jetzt gegen den gesetzten Wert.
5. Nach dem ersten Lauf `wallet:verify` und die Zeile „Fristablauf abgearbeitet" im Log
   gegenprüfen.

> Beim Scharfstellen mitbedenken: Am 2026-10-12 stehen fünf Reservierungen des Käufers
> Tenant 2 offen (Kaufbelege 77–81, Leads 1791/1795/1798/1801/1803, Frist 2026-10-18,
> je 15,00 EUR). Ein Cutover auf den 2026-10-11 oder früher rechnet sie beim ersten
> 03:00-Lauf nach dem 18.10. ab, obwohl zu diesen Leads kein Anrufversuch existiert.
> Entweder vorher einen echten Anruf nachweisen oder den Cutover hinter deren
> Auslieferung legen.
>
> Die Grenze ist ausgemessen (Ticket #85, 2026-10-12): Alle fünf tragen
> `delivered_at` am **2026-10-11** (spätestes 23:15:33), Frist jeweils 18.10.
> Der Trockenlauf aus Punkt 4, gegen den ersten Lauf nach Fristablauf gerechnet
> (19.10. 03:00), ergibt `2026-10-11` → 5 Leads / 5 Käufe / **75,00 EUR**,
> `2026-10-12` → 0 / 0 / 0,00 EUR, `2026-10-13` → 0 / 0 / 0,00 EUR
> (`Carbon::parse("2026-10-12")` ist 00:00 Uhr, siehe `ResolveExpiredLeads::expiredLeads()`).
> Solange `call_attempts` leer ist, kann das Datum aus Punkt 2 nur **heute oder
> später** liegen — der Fallstrick trifft also nur, wenn jemand den Cutover von
> Hand früher legt als den ersten gemessenen Anrufversuch. Genau das nicht tun.

> Erledigte Altlast (Ticket #76, 2026-10-12): Die vier Bestandsleads mit abgelaufener
> Frist (326, 1026, 1311, 1316) bleiben dauerhaft `open` — ihr `delivered_at` liegt vor
> jedem künftigen Cutover, der Lauf fasst sie nicht mehr an. Die beiden hängenden
> Reservierungen der Kaufbelege 75 und 76 (Lead 1316 bzw. 1311, Käufer Tenant 2) wurden
> über `PurchaseService::release()` freigegeben; `wallets.reserved_cents` von Wallet 2
> fiel dabei von 12113 auf 9113 Cent. Zu 326 und 1026 gibt es keinen Kaufbeleg. Nicht
> abgerechnet wurde dabei nichts — die Käufer hatten das Anrufpanel nie.

### Fremdschlüssel: nie für ein Aufräumen dauerhaft droppen (Ticket #83, gemessen am 2026-10-12)

**Regel, ohne Ausnahme:** Ein Fremdschlüssel wird live niemals gedroppt, um Zeilen löschen
zu können. Wer eine Zeile nicht loswird, hat kein Schema-Problem, sondern ein
Datenproblem — und löst es über den Fachweg (eine offene Reservierung über
`PurchaseService::release()`, einen Lead über den `LeadRetentionService`), nicht mit
`ALTER TABLE ... DROP FOREIGN KEY`. Muss ein Fremdschlüssel wirklich weichen, gehört das
in eine Migration, die ihn am Ende wieder anlegt, nicht in eine SQL-Sitzung.

Der Grund steht im Live-Stand: Gemessen am 2026-10-12 trug die Live-Datenbank alle 152
Migrationen als gelaufen, hatte aber nur **24 der 153 Fremdschlüssel**, die ein frischer
Migrationslauf anlegt. `leads` hatte keinen einzigen, `lead_purchases` zwei von sechs
(`buyer_tenant_id` und `lead_id` fehlten), ebenso `orders`, `subscriptions`,
`transactions`, `funnels` und die Rollen-Pivots. Vollständig waren nur `wallets`,
`wallet_transactions`, `payment_methods` und `settlements` — die Geldseite war
abgesichert, die Lead- und Abo-Seite nicht. Was dadurch passieren kann, ist in Ticket #78
passiert: Ein Lead wurde gelöscht, nahm per Kaskade einen reservierten Kaufbeleg mit, und
`wallets.reserved_cents` blockte danach 16,13 EUR ohne Beleg.

Nachgezogen wird das von
`database/migrations/2026_10_12_130000_restore_missing_foreign_keys.php`. Die Migration
trägt die Soll-Liste als Datenfeld, legt nur an, was fehlt, und ist beliebig oft
wiederholbar. Verwaiste Zeilen löscht sie **nicht**: Die betroffene Beziehung bleibt
offen und wird mit Zeilenzahl und Beispielwerten gemeldet. Ein Abbruch wäre falsch — er
hielte live jede folgende Migration wegen Altdaten auf.

**So wird gegengeprüft** (Soll-Stand ist immer ein frischer Migrationslauf, nie die
lokale Entwicklungsdatenbank — die driftet mit):

```bash
# 1. Prüfdatenbank mit dem Soll-Stand aufbauen
mysql -uroot -e "CREATE DATABASE widileads_fkref CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
DB_DATABASE=widileads_fkref php artisan migrate --force

# 2. Fremdschlüssel beider Seiten vergleichen (ON UPDATE 'NO ACTION' heißt auf der
#    MariaDB des Live-Servers 'RESTRICT' — ohne Normalisierung weicht jede Zeile ab)
cat > /tmp/fkdump.sql <<'SQL'
SELECT CONCAT(kcu.TABLE_NAME, ' | ', kcu.COLUMN_NAME, ' -> ', kcu.REFERENCED_TABLE_NAME,
  '(', kcu.REFERENCED_COLUMN_NAME, ') ', REPLACE(rc.DELETE_RULE, 'NO ACTION', 'RESTRICT'))
FROM information_schema.KEY_COLUMN_USAGE kcu
JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
  ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
WHERE kcu.CONSTRAINT_SCHEMA = DATABASE() AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY 1;
SQL
mysql -uroot -N -B widileads_fkref < /tmp/fkdump.sql | sort > /tmp/fk_soll.txt
scp /tmp/fkdump.sql leads:/tmp/
ssh leads 'cd /home/leads/htdocs/leads.widimedia.com && eval $(grep -E "^DB_(DATABASE|USERNAME|PASSWORD)=" .env | sed -e "s/^/export /") && mysql -u"$DB_USERNAME" -p"$DB_PASSWORD" -N -B "$DB_DATABASE" < /tmp/fkdump.sql' | sort > /tmp/fk_live.txt
comm -23 /tmp/fk_soll.txt /tmp/fk_live.txt   # fehlt live

mysql -uroot -e "DROP DATABASE widileads_fkref;"
```

Eine Schema-Änderung an Fremdschlüsseln wird außerdem **immer gegen eine Kopie des
Live-Stands** geprobt, nicht nur gegen einen frischen Migrationslauf — sonst fällt die
Drift erst auf dem Live-Server auf:

```bash
ssh leads 'cd /home/leads/htdocs/leads.widimedia.com && eval $(grep -E "^DB_(DATABASE|USERNAME|PASSWORD)=" .env | sed -e "s/^/export /") && mysqldump -u"$DB_USERNAME" -p"$DB_PASSWORD" --single-transaction --no-tablespaces --skip-lock-tables "$DB_DATABASE" | gzip -9' > /tmp/live.sql.gz
mysql -uroot -e "CREATE DATABASE widileads_livefk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip -c /tmp/live.sql.gz | mysql -uroot widileads_livefk
DB_DATABASE=widileads_livefk php artisan migrate --force
mysql -uroot -e "DROP DATABASE widileads_livefk;"
```

### Verwaiste Altdaten vor dem Fremdschlüssel aufräumen: `db:cleanup-orphans` (Ticket #87)

Die Nachzieh-Migration konnte am 2026-10-12 nur **107 von 128** Fremdschlüsseln anlegen.
Die übrigen 21 blieben offen, weil in der Zeit ohne Fremdschlüssel Mandanten, Nutzer,
Funnels und Leads gelöscht worden sind, ohne dass ihre Kinder mitgingen — die Datenbank
hätte die Zeilen beim Anlegen zurückgewiesen. Dafür gibt es
`App\Console\Commands\CleanupOrphanedRows`:

```bash
sudo -u leads /usr/bin/php8.4 artisan db:cleanup-orphans            # Probe, schreibt nichts
sudo -u leads /usr/bin/php8.4 artisan db:cleanup-orphans --apply --force
sudo -u leads /usr/bin/php8.4 artisan migrate --force               # zieht die Fremdschlüssel nach
```

**Die Regel des Laufs:** angewandt wird genau das, was der fehlende Fremdschlüssel selbst
vorschreibt. `cascade` heißt, die Zeile wäre mit dem Elternteil gegangen — sie wird
gelöscht. `set null` heißt, der Verweis wäre geleert worden — die Spalte geht auf `NULL`.
Der Lauf erfindet damit keine Fachlogik; er holt die Kaskade nach, die zum Zeitpunkt des
Löschens fehlte. Ein Lead mit Kaufbeleg wird nie angefasst (`lead_purchases` ist
`restrict`): Er bleibt stehen und wird gemeldet.

Die beiden `restrict`-Beziehungen der Geldseite schreiben nichts vor und werden von Hand
entschieden, ohne einen Beleg zu löschen:

- `transactions.tenant_id` wird aus `orders.tenant_id` des eigenen Belegs
  richtiggestellt. Live waren das drei Zeilen auf Mandant 9, deren Bestellungen alle auf
  Mandant 2 lauten — der Wert ist herleitbar, die Spalte muss nicht `nullable` werden.
- `user_stripe_data.tenant_id` geht auf `NULL`. Die Zeile bleibt als Beleg des
  Stripe-Kunden, aber kein Mandant beansprucht sie mehr. Umhängen wäre falsch:
  `PaymentMethodService` liest `where tenant_id = …` mit `first()` und könnte danach den
  alten Kunden statt des aktuellen erwischen.

Ohne `--apply` läuft dieselbe Folge in einer Transaktion und wird am Ende zurückgedreht —
die gemeldeten Zahlen sind damit die echten. Der Lauf ist wiederholbar: ein zweiter Aufruf
findet nichts mehr.

> Probe gegen eine Kopie des Live-Stands am 2026-10-12 (Ticket #87): **5994 Zeilen in 23
> Beziehungen**. Größte Posten: 4860 `lead_answers` (2400 auf längst gelöschte Leads, 2460
> als Kinder der gleich gelöschten), 479 `lead_state_log`, 205 `leads` der gelöschten
> Demo-Mandanten 6 und 10 aus dem `PortalDemoSeeder` (Kontaktadressen auf `@demo.test`,
> Funnel 3 ebenfalls gelöscht, **kein einziger Kaufbeleg darauf**), 117 `session_events`.
> Danach zieht `migrate` **128 von 128** Fremdschlüsseln nach, und der Schema-Vergleich
> oben liefert eine leere Differenz in beide Richtungen (153 zu 153). `wallet:verify`
> meldet danach „3 Wallet(s) geprueft, keine Abweichung"; `OperatorRevenueReport` bleibt
> unverändert (es rechnet auf `lead_purchases`), und das `BuyerBilling` des Käufers
> Mandant 2 führt im September jetzt **23 statt 20 Zahlungsbelege** — die drei auf
> Mandant 9 gestrandeten über 1000, 1000 und 1500 EUR waren dem Käufer vorher in seiner
> eigenen Belegliste nicht zu sehen.

### Rollback

Es gibt **keinen** Rollback-Mechanismus mit vorgehaltenen Releases: ausgerollt wird mit
`git pull`, es liegt nur ein Stand im Verzeichnis (`php dep rollback` läuft ins Leere).
Ein Rückfall ist derselbe Weg rückwärts:

```bash
git checkout <voriger-commit>     # oder: git reset --hard <voriger-commit> auf main
chown -R leads:leads /home/leads/htdocs/leads.widimedia.com
sudo -u leads npm run build
sudo -u leads /usr/bin/php8.4 artisan optimize:clear && sudo -u leads /usr/bin/php8.4 artisan optimize
systemctl restart horizon-widileads
```

**Migrationen laufen dabei nicht zurück.** Ein Rückfall hilft gegen fehlerhaften Code,
nicht gegen eine fehlerhafte Migration. Für die gilt Abschnitt 3.
---

## 2. Horizon und Hintergrundverarbeitung

### Aufbau

**Live läuft Horizon als systemd-Dienst, nicht unter Supervisor** (geprüft am
2026-10-12). Die Unit `/etc/systemd/system/horizon-widileads.service` ist `enabled`:

```
[Service]
User=leads
Group=leads
WorkingDirectory=/home/leads/htdocs/leads.widimedia.com
ExecStart=/usr/bin/php /home/leads/htdocs/leads.widimedia.com/artisan horizon
ExecStop=/usr/bin/php .../artisan horizon:terminate
Restart=always
```

Der Supervisor auf der Maschine bedient fremde Projekte (minber, sanitaerfinden,
kasernencheck) — **kein** Eintrag für widileads. Der `provision:supervisor`-Task aus
`deploy.php` unten ist Boilerplate und auf diesem Server nie gelaufen:

```
[program:horizon]
command=php {deploy_path}/current/artisan horizon
autostart=true
autorestart=true
stopwaitsecs=60
stdout_logfile={deploy_path}/log/horizon.log
```

Konfiguration in `config/horizon.php`: ein Supervisor, eine Queue (`default`),
`balance=auto`. In `production` bis zu 10 Prozesse, in `local` bis zu 3.

### Handgriffe

```bash
# live (ssh leads), im Verzeichnis /home/leads/htdocs/leads.widimedia.com
systemctl status horizon-widileads     # laeuft er, und seit wann?
systemctl restart horizon-widileads    # Neustart - nach JEDEM Rollout
sudo -u leads /usr/bin/php8.4 artisan horizon:status      # Sicht der Anwendung
sudo -u leads /usr/bin/php8.4 artisan horizon:terminate   # sanft beenden, systemd startet neu
sudo -u leads /usr/bin/php8.4 artisan queue:failed        # fehlgeschlagene Jobs
sudo -u leads /usr/bin/php8.4 artisan queue:retry all     # alle erneut einreihen
journalctl -u horizon-widileads -n 100 --no-pager
```

`ActiveEnterTimestamp` ist die wichtigste Zahl davon: ist der Dienst älter als der
letzte Rollout, verarbeitet er Jobs mit altem Code.

Die Oberfläche liegt unter `/horizon` und ist nur für Plattform-Admins erreichbar.

### Geplante Aufgaben

Alles hängt am einen Cron-Eintrag. Fällt der aus, fällt **alles** darunter aus, ohne
jede Fehlermeldung.

| Befehl | Takt | Wirkung bei Ausfall |
|---|---|---|
| `app:release-expired-lead-reservations` | minütlich | Reservierungen verfallen nicht; Leads bleiben für andere Käufer blockiert |
| `app:abandon-stale-funnel-sessions` | alle 5 Minuten | angefangene Strecken bleiben ewig „offen" |
| `app:apply-lead-retention` | täglich | Aufbewahrungsfrist greift nicht — **datenschutzrelevant** |
| `app:generate-sitemap` | alle 2 Stunden | Sitemap veraltet |
| `app:metrics-beat` | täglich | Kennzahlen fehlen |
| `app:cleanup-local-subscription-statuses` | stündlich | abgelaufene Abos bleiben aktiv |
| `app:sync-seat-based-subscription-quantities` | stündlich | Sitzplatzzahlen laufen auseinander |
| `app:local-subscription-expiring-soon-reminder` | täglich | Erinnerungsmails bleiben aus |
| `app:auto-purchase-leads` | alle 5 Minuten | Autokauf greift nicht; der Käufer verliert Abschlüsse an frischere Leads |
| `app:settle-elapsed-complaint-periods` | täglich 01:00 | Käufe nach Ablauf der Reklamationsfrist werden nicht abgeschlossen — **Abrechnung bleibt liegen** |
| `leads:resolve-expired` | täglich 03:00 | Leads mit abgelaufener Kontaktfrist werden nicht abgerechnet — **Abrechnung bleibt liegen**. Ohne `LEAD_CALLS_ENFORCE_FROM` findet der Lauf nichts (Ticket #73) |
| `leads:send-deadline-reminders` | stündlich | Käufer werden nicht vor Fristende erinnert und verlieren das Geld ohne Warnung |
| `wallet:verify` | täglich 04:00 | ein abgewichener Wallet-Saldo fällt niemandem auf |
| `wallet:settle` | wöchentlich (Wochentag/Zeit aus `config/wallet.php`) | offene Postpaid-Beträge werden nicht eingezogen — **Geld bleibt offen** |
| `wallet:settle --charge-only` | stündlich | zweiter Einzugsversuch und angekündigte Lastschriften bleiben aus |

Die vollständige, immer aktuelle Liste liefert `php artisan schedule:list` — die Tabelle
oben nennt die Wirkung, nicht den Zeitplan als Quelle der Wahrheit.

**Erste Prüfung bei „irgendetwas läuft nicht mehr":**

```bash
crontab -u leads -l                           # steht der Eintrag noch da?
grep CRON /var/log/syslog | grep '(leads)'    # feuert er auch (minuetlich eine Zeile)?
php artisan schedule:list                     # was waere faellig?
php artisan schedule:run                      # einmal von Hand
```

### Zwei bekannte Lücken

Beide stehen im [Backlog](../BACKLOG.md) und sind **nicht** behoben:

- **`horizon:snapshot` ist nicht eingeplant.** Ohne
  `Schedule::command('horizon:snapshot')->everyFiveMinutes()` bleibt die Metrics-Seite
  von Horizon dauerhaft leer. Die Verarbeitung selbst ist davon nicht betroffen.
- **`config/horizon.php` kennt keine `staging`-Umgebung.** In einer Umgebung mit
  `APP_ENV=staging` startet Horizon **keinen einzigen Supervisor** und verarbeitet keine
  Jobs — schweigend. Vor dem ersten Staging-Deploy ergänzen.

---

## 3. Sicherung und Wiederherstellung

**Ziel: wieder betriebsbereit in unter vier Stunden.**

> **Ist-Stand:** Es ist **keine** automatische Sicherung eingerichtet. Weder ein
> Backup-Paket im `composer.json` noch ein Cron-Eintrag. Dieser Abschnitt beschreibt das
> Verfahren, das vor dem Produktivstart eingerichtet werden **muss**, nicht eines, das
> läuft.

### Was gesichert werden muss

| Gegenstand | Wo | Ohne Sicherung verloren |
|---|---|---|
| MySQL-Datenbank | Datenbankserver | alles: Leads, Käufe, Guthabenkonto, Protokolle |
| `storage/app/public` | Anwendungsserver | hochgeladene Logos und Bilder der Funnels |
| `.env` | Anwendungsserver | Schlüssel und Zugangsdaten — **ohne `APP_KEY` sind verschlüsselte Konfigurationswerte unlesbar** |
| `database/templates/` | im Repository | nichts, liegt in Git |

Redis muss **nicht** gesichert werden: Er trägt Cache und Warteschlange, keinen Bestand,
der nicht rekonstruierbar wäre. Ein Verlust kostet laufende Jobs, keine Daten.

### Sicherungsplan

- **Täglich** ein vollständiger `mysqldump`, verschlüsselt, an einen Ort **außerhalb**
  des Anwendungsservers. Eine Sicherung auf derselben Maschine ist keine.
- **Binärprotokoll** (`binlog`) von MySQL aktiviert und mitgesichert, damit ein
  Wiederherstellungspunkt zwischen zwei Vollsicherungen möglich ist.
- **Aufbewahrung:** 14 Tagessicherungen, 3 Monatssicherungen — und bewusst nicht länger.
  Eine Sicherung friert den Bestand ein, auch die personenbezogenen Daten darin. Die
  Aufbewahrungsfrist der Leads (`config('funnel.lead.retention_days')`, Vorgabe 730 Tage)
  löscht nur die laufende Datenbank; alte Sicherungen behalten, was dort längst weg ist.
  Je kürzer der Aufbewahrungszeitraum der Sicherungen, desto kleiner dieser Schatten.
- **Nach jeder Wiederherstellung `php artisan app:apply-lead-retention` laufen lassen.**
  Sonst sind Leads wieder da, deren Frist zwischen Sicherung und Wiederherstellung
  abgelaufen ist.
- **Probewiederherstellung vierteljährlich** auf einem Wegwerf-Server. Eine Sicherung,
  aus der nie zurückgespielt wurde, ist eine Vermutung.

### Wiederherstellung, Schritt für Schritt

Zeitangaben sind das Budget, nicht die Messung.

| Schritt | Budget |
|---|---|
| 1. Server bereitstellen, `php dep provision` | 60 min |
| 2. `.env` aus der Sicherung einspielen, `APP_KEY` prüfen | 10 min |
| 3. Datenbank anlegen, Dump einspielen | 45 min |
| 4. Bei Bedarf Binlog bis zum Zeitpunkt X nachfahren | 30 min |
| 5. `php dep deploy` | 20 min |
| 6. `storage/app/public` zurückspielen | 15 min |
| 7. `app:apply-lead-retention` laufen lassen | 5 min |
| 8. Prüfliste unten abarbeiten | 30 min |
| **Summe** | **3 h 35 min** |

```bash
# Schritt 3, ausdruecklich OHNE migrate:fresh
mysql -u root -p funnel_production < dump.sql

# Schritt 5 setzt danach nur noch fehlende Migrationen
php artisan migrate --force
```

> **Niemals `migrate:fresh` zur Wiederherstellung.** Der Befehl legt das Schema neu an
> und wirft den eben eingespielten Bestand weg. Der `DestructiveCommandGuard` blockt ihn
> im Betrieb — dieser Schutz ist der Grund, warum er existiert, und kein Hindernis, das
> man umgeht.

### Prüfliste nach der Wiederherstellung

```bash
php artisan migrate:status                    # keine offenen Migrationen
php artisan about                             # Cache, Queue, Datenbank verbunden
supervisorctl status horizon                  # laeuft
crontab -u leads -l                           # Eintrag vorhanden
curl -I https://<domain>/f/<token>            # oeffentliche Strecke antwortet 200
```

Fachlich zusätzlich prüfen:

- Guthabenstand eines Käufers stimmt mit der Summe seiner Buchungen überein.
- Ein verkaufter Lead zeigt seinem Käufer Klartext-Kontaktdaten, ein nicht gekaufter
  nicht.
- Das Zustandsprotokoll eines Leads endet auf demselben Zustand wie `leads.lead_state`.

---

## 4. Runbook: Ausfall des Stripe-Webhooks

### Was ausfällt

`POST /api/payments-providers/stripe/webhook` verarbeitet Abo- und Bestellereignisse
**im Request**, nicht über die Warteschlange. Antwortet der Endpunkt nicht oder mit
einem Fehler, passiert bis zur Nachverarbeitung Folgendes **nicht**:

- Guthabenpakete werden nicht gebucht (`BookCreditsOnOrder`). Der Käufer hat bezahlt und
  sieht kein Guthaben — **er kann keine Leads kaufen.**
- Abo-Zustände laufen auseinander: gekündigte Abos bleiben aktiv, bezahlte Verlängerungen
  fehlen.
- Rechnungen entstehen nicht.

### Erkennen

- Stripe-Dashboard → *Developers* → *Webhooks*: Fehlerquote und letzte Antworten.
- Stripe wiederholt fehlgeschlagene Zustellungen mit wachsendem Abstand über mehrere
  Tage. **Innerhalb dieses Fensters geht nichts verloren** — das ist die eigentliche
  Entwarnung.
- Im Anwendungslog: gehäufte 400er auf dem Webhook-Pfad heißen ungültige Signatur, nicht
  Ausfall. Dann stimmt das Webhook-Geheimnis nicht mehr.

### Sofortmaßnahmen

1. **Erreichbarkeit prüfen.** Antwortet die Domain? Läuft PHP-FPM? Ist die Route durch
   ein Rate-Limit blockiert?
2. **Signatur prüfen.** Nach einem Wechsel des Endpunkts in Stripe ändert sich das
   Webhook-Geheimnis. Stimmt es mit dem in der Anwendung hinterlegten überein?
3. **Datenbank prüfen.** Bei Datenbankausfall scheitert der Handler in der Transaktion
   und antwortet mit 500. Dann ist der Webhook das Symptom, nicht die Ursache.
4. Nach der Behebung: einen Testevent aus dem Stripe-Dashboard senden und die Antwort
   `200` bestätigen.

### Nacharbeiten

Im Stripe-Dashboard die fehlgeschlagenen Ereignisse erneut zustellen
(*Webhooks → Failed → Resend*), oder:

```bash
stripe events resend <event_id>
```

**Das erneute Zustellen ist sicher.** Zwei Schutzmaßnahmen greifen:

- Die Aufladung im Wallet ist über ihren Idempotenzschlüssel abgesichert — dieselbe
  Bestellung erzeugt auch bei mehrfacher Zustellung genau eine Buchung.
- Abo-Ereignisse laufen unter `lockForUpdate`, und `isSuperfluousEvent` verwirft
  Ereignisse, die einen bereits erreichten Zustand nochmals setzen wollen.

Reihenfolge beim Nachfahren: **älteste Ereignisse zuerst.** Die Abo-Verarbeitung ist auf
zeitliche Reihenfolge ausgelegt.

### Nachher prüfen

```sql
-- Bestellungen ohne zugehoerige Aufladung im Wallet
SELECT o.id, o.uuid, o.tenant_id, o.created_at
FROM orders o
LEFT JOIN wallet_transactions t
  ON t.reference_type = 'App\\Models\\Order' AND t.reference_id = o.id
  AND t.type = 'topup'
WHERE o.tenant_id IS NOT NULL
  AND t.id IS NULL
  AND o.created_at > NOW() - INTERVAL 7 DAY;
```

Bleibt eine Lücke, wird sie **nicht** von Hand in die Datenbank geschrieben. Der Weg ist
eine Korrekturbuchung im Adminbereich unter *Wallets*, die über
`App\Services\Wallet\WalletService::post()` läuft und einen Beleg und einen Grund
trägt. Das Journal ist unveränderlich — eine stille Direktbuchung wäre genau die Sorte
Eintrag, die später niemand mehr erklären kann, und der nächtliche Lauf
`wallet:verify` meldet sie ohnehin als Abweichung.

---

## 5. Was dieses Handbuch nicht abdeckt

- **Überwachung und Alarmierung.** Es gibt keine. Ohne sie merkt ein Ausfall des
  Cron-Eintrags oder des Webhooks niemand, bis sich ein Käufer meldet.
- **Mehrere Anwendungsserver.** `deploy.php` kennt genau einen Host.
- **Wiederherstellung einzelner Mandanten.** Das Verfahren oben stellt den gesamten
  Bestand wieder her.
