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
| `leads:resolve-expired` | täglich 03:00 | Leads mit abgelaufener Kontaktfrist werden nicht abgerechnet — **Abrechnung bleibt liegen** |
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
