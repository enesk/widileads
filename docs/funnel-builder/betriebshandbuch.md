# Betriebshandbuch

Was ein Mensch wissen muss, der die Plattform ausrollt, am Laufen hält und nach einem
Ausfall wieder hochbringt (FB-044).

Dieses Dokument beschreibt den **Ist-Stand des Repositories**. Wo etwas noch nicht
eingerichtet ist, steht das ausdrücklich dabei — ein Handbuch, das eine Automatik
beschreibt, die es nicht gibt, ist schlimmer als keines.

---

## 1. Deployment

### Womit

[Deployer](https://deployer.org) über `deploy.php`, auf Basis von `recipe/laravel.php`.
Aufruf aus dem Projektverzeichnis:

```bash
php dep provision    # einmalig: Server einrichten
php dep deploy       # jeder weitere Rollout
```

### Was vorher konfiguriert sein muss

Der Konfigurationsblock steht ganz oben in `deploy.php` und trägt noch die
Beispielwerte aus SaaSykit. **Ohne Anpassung deployt der Befehl gegen `1.2.3.4` und
`git@github.com:username/saasykit.git`.**

| Wert | Bedeutung |
|---|---|
| `$host` | IP oder Hostname des Zielservers |
| `$domain` | Domain der Anwendung |
| `$remoteUser` | Benutzer für den Deploy (Vorgabe `deployer`) |
| `$deployPath` | Zielverzeichnis, Vorgabe `~/app` |
| `$repository` | Repository in SSH-Schreibweise |
| `$phpVersion` | `8.4` |

Node wird in Version 23 installiert, PHP bekommt zusätzlich `php8.4-redis`.

### Was ein Deploy von selbst tut

In dieser Reihenfolge:

1. `artisan migrate` — danach automatisch `artisan db:seed`. Der `DatabaseSeeder` ruft
   seine Seeder über `callOnce` auf; ein wiederholter Lauf legt nichts doppelt an.
2. `npm:build` — Vite-Assets.
3. Nach Erfolg: `artisan horizon:terminate` (Horizon startet mit neuem Code neu),
   `crontab:sync`, `app:generate-sitemap`, `app:export-configs`.
4. Bei Fehlschlag: `deploy:unlock`.

Der Cron bekommt genau einen Eintrag:

```
* * * * * cd {{current_path}} && php artisan schedule:run >> /dev/null 2>&1
```

### Umgebungsvariablen

Vorlage ist `.env.example`. Für den Betrieb gilt zusätzlich:

| Variable | Wert im Betrieb | Warum |
|---|---|---|
| `APP_ENV` | `production` | steuert Fehlerausgabe und den Schutz destruktiver Befehle |
| `APP_LOCALE` | `de` | die Oberfläche ist deutsch |
| `QUEUE_CONNECTION` | `redis` | `.env.example` steht auf `sync`; damit liefe jeder Job im Request |
| `CACHE_DRIVER` | `redis` | |
| `SESSION_DRIVER` | `redis` oder `database` | `file` skaliert nicht über mehrere Prozesse |
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

### Rollback

Deployer hält die letzten Releases vor. Ein Rollback auf das vorige Release:

```bash
php dep rollback
```

**Migrationen laufen dabei nicht zurück.** Ein Rollback hilft gegen fehlerhaften Code,
nicht gegen eine fehlerhafte Migration. Für die gilt Abschnitt 3.

---

## 2. Horizon und Hintergrundverarbeitung

### Aufbau

Horizon läuft unter Supervisor. `provision:supervisor` legt
`/etc/supervisor/conf.d/horizon.conf` an:

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
supervisorctl status horizon          # laeuft er?
supervisorctl restart horizon         # Neustart
php artisan horizon:status            # Sicht der Anwendung
php artisan horizon:terminate         # sanft beenden, Supervisor startet neu
php artisan queue:failed              # fehlgeschlagene Jobs
php artisan queue:retry all           # alle erneut einreihen
tail -f {deploy_path}/log/horizon.log
```

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

**Erste Prüfung bei „irgendetwas läuft nicht mehr":**

```bash
crontab -l                                    # steht der Eintrag noch da?
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
crontab -l                                    # Eintrag vorhanden
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
