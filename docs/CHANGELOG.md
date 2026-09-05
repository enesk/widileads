# Changelog

Ein Eintrag je abgeschlossenem Ticket. Der Eintrag ist Teil der Definition of Done —
ein Ticket gilt ohne ihn nicht als fertig.

## Format

Pro Ticket ein Abschnitt, neueste Einträge oben:

```markdown
### FB-### — <Ticketttitel>

**Was:** Was wurde gebaut oder geändert (ein bis drei Sätze).
**Warum:** Der fachliche Grund bzw. die Anforderung aus dem Ticket.
**Neue Config-Keys:** `config/funnel.php` → `key.pfad` (Standardwert, env-Variable) —
oder „keine".
```

Weitere Konventionen:

- Eine Zeile „**Migrationen:**" ergänzen, wenn das Ticket Tabellen anlegt oder ändert.
- Breaking Changes an der API mit **BREAKING** am Zeilenanfang kennzeichnen.
- Der Eintrag beschreibt das Ergebnis, nicht den Arbeitsweg.

## Einträge

### FB-001 — Projekt-Setup

**Was:** Blog, Roadmap, Announcements und Referral sind per Feature-Flag abschaltbar
(Default: aus). Bei deaktiviertem Flag werden weder die öffentlichen Routen (`/blog`
und `/roadmap` liefern 404) noch die Navigationseinträge in Frontend und Admin-Panel
registriert. Larastan steht auf Level 6 mit eingefrorener Baseline für den
Bestandscode, neu ist `composer check` (Pint + PHPStan + Tests).

**Warum:** Der Funnel Builder braucht keines der SaaSykit-Marketing-Module. Sie
bleiben im Code (kein Entfernen), sind aber standardmäßig unsichtbar, damit sie weder
Angriffsfläche noch Pflegeaufwand erzeugen. Ein einheitlicher Quality-Gate-Befehl hält
Formatierung, statische Analyse und Tests grün.

**Neue Config-Keys:** `config/funnel.php` → `features.blog`, `features.roadmap`,
`features.announcements`, `features.referral` (jeweils `false`,
`FUNNEL_FEATURE_*_ENABLED`). Zusätzlich ist `config/app.php` → `locale` jetzt über
`APP_LOCALE` steuerbar (Default `en`, im Funnel Builder `de`).

**Migrationen:** keine.

Ist-Zustand von Larastan und Ergebnis der Horizon/Redis-Prüfung: siehe
[BACKLOG.md](BACKLOG.md).

### FB-003 — DestructiveCommandGuard

**Was:** `migrate:fresh`, `migrate:refresh`, `migrate:reset` und `db:wipe` brechen in
jeder Umgebung mit Exit-Code 1 und einer deutschen Meldung ab — auch mit `--force`.
`App\Providers\DestructiveCommandGuardServiceProvider` ersetzt die vier Befehle durch
`App\Console\BlockedDestructiveCommand`. Einzige Ausnahme: `APP_ENV=testing` **und**
`FUNNEL_ALLOW_DESTRUCTIVE=1`.

**Warum:** Ein versehentliches `migrate:fresh` vernichtet gekaufte Leads und damit
Umsatzdaten, die nicht rekonstruierbar sind. Der Schutz greift bewusst auch lokal, weil
dort häufig mit Produktionsdumps gearbeitet wird.

**Neue Config-Keys:** `config/funnel.php` → `allow_destructive_commands` (`false`,
`FUNNEL_ALLOW_DESTRUCTIVE`) — in `phpunit.xml` und `.env.testing` auf `1` gesetzt,
damit die Testsuite ihre Datenbank weiterhin aufbauen kann.

**Migrationen:** keine.

### FB-004 — config/funnel.php

**Was:** Zentrale Konfigurationsdatei für alle Schwellwerte der Plattform, jeder Key mit
env-Fallback und erklärendem Kommentar. `tests/Unit/FunnelConfigTest.php` sichert Keys,
Defaults und Kommentare ab.

**Warum:** Die Schwellwerte (Lead-Preis, Reservierungsdauer, Kontaktpflichten) sind
kaufmännische Parameter, die sich ohne Deployment ändern können müssen. Kein späteres
Ticket darf diese Werte hartkodieren.

**Neue Config-Keys:** `config/funnel.php` →

| Key                                       | Standardwert | env-Variable                              |
| ----------------------------------------- | ------------ | ----------------------------------------- |
| `lead.default_price`                      | 15.00        | `FUNNEL_LEAD_DEFAULT_PRICE`               |
| `lead.reservation_ttl`                    | 10 (Minuten) | `FUNNEL_LEAD_RESERVATION_TTL`             |
| `lead.retention_days`                     | 730          | `FUNNEL_LEAD_RETENTION_DAYS`              |
| `lead.stale_after_days`                   | 3            | `FUNNEL_LEAD_STALE_AFTER_DAYS`            |
| `public.rate_limit_per_hour`              | 20           | `FUNNEL_PUBLIC_RATE_LIMIT_PER_HOUR`       |
| `public.min_seconds_before_submit`        | 30           | `FUNNEL_PUBLIC_MIN_SECONDS_BEFORE_SUBMIT` |
| `call.answered_after_seconds`             | 30           | `FUNNEL_CALL_ANSWERED_AFTER_SECONDS`      |
| `call.max_failed_attempts`                | 3            | `FUNNEL_CALL_MAX_FAILED_ATTEMPTS`         |
| `call.min_gap_hours`                      | 2            | `FUNNEL_CALL_MIN_GAP_HOURS`               |
| `call.min_days`                           | 2            | `FUNNEL_CALL_MIN_DAYS`                    |
| `call.deadline_days`                      | 7            | `FUNNEL_CALL_DEADLINE_DAYS`               |

Für `public.rate_limit_per_hour` gab das Ticket keinen Standardwert vor; 20 Submits pro
IP und Stunde sind für einen Quiz-Funnel großzügig und stoppen trotzdem einfache Bots.

**Migrationen:** keine.
