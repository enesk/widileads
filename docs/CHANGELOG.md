# Changelog

## Sprint 1 - Fundament

### FB-001 - Projekt-Setup

**Was:** Blog, Roadmap, Announcements und Referral sind per Feature-Flag
abschaltbar (Default: aus). Bei deaktiviertem Flag werden weder die
oeffentlichen Routen (`/blog`, `/roadmap` liefern 404) noch die
Navigationseintraege in Frontend und Admin-Panel registriert. Larastan steht auf
Level 6 mit eingefrorener Baseline fuer den Bestandscode. Neu:
`composer check` (Pint + PHPStan + Tests). `resources/lang/` wurde nach `lang/`
konsolidiert, weil die Dateien unter `lang/` sonst wirkungslos waren.

**Warum:** Der Funnel Builder braucht keine der SaaSykit-Marketing-Module. Sie
bleiben im Code (kein Entfernen), sind aber standardmaessig unsichtbar, damit
sie weder Angriffsflaeche noch Pflegeaufwand erzeugen. Ein einheitlicher
Quality-Gate-Befehl haelt Formatierung, statische Analyse und Tests gruen.

**Neue Config-Keys:** `funnel.features.blog`, `funnel.features.roadmap`,
`funnel.features.announcements`, `funnel.features.referral`
(Env: `FUNNEL_FEATURE_*_ENABLED`, Default `false`),
`app.locale` ist jetzt ueber `APP_LOCALE` steuerbar (Default `en`, im
Funnel Builder `de`).

**Ist-Zustand Larastan und Horizon/Redis:** siehe `docs/BACKLOG.md`.

### FB-003 - DestructiveCommandGuard

**Was:** `migrate:fresh`, `migrate:refresh`, `migrate:reset` und `db:wipe`
brechen in jeder Umgebung mit Exit-Code 1 und einer deutschen Meldung ab - auch
mit `--force`. Umgesetzt ueber `App\Providers\DestructiveCommandGuardServiceProvider`,
der die vier Befehle durch `App\Console\BlockedDestructiveCommand` ersetzt.
Einzige Ausnahme: `APP_ENV=testing` **und** `FUNNEL_ALLOW_DESTRUCTIVE=1`.

**Warum:** Ein versehentliches `migrate:fresh` vernichtet gekaufte Leads und
damit Umsatzdaten, die nicht rekonstruierbar sind. Der Schutz greift bewusst
auch lokal, weil dort haeufig mit Produktionsdumps gearbeitet wird.

**Neue Config-Keys:** `funnel.allow_destructive_commands`
(Env: `FUNNEL_ALLOW_DESTRUCTIVE`, Default `false`; in `phpunit.xml` und
`.env.testing` auf `1` gesetzt, damit die Testsuite ihre Datenbank aufbauen kann).

### FB-004 - config/funnel.php

**Was:** Zentrale Konfigurationsdatei fuer alle Schwellwerte der Plattform, jeder
Key mit Env-Fallback und erklaerendem Kommentar. `tests/Unit/FunnelConfigTest.php`
sichert Keys, Defaults und Kommentare ab.

**Warum:** Die Schwellwerte (Lead-Preis, Reservierungsdauer, Kontaktpflichten)
sind kaufmaennische Parameter, die sich ohne Deployment aendern koennen muessen.
Kein spaeteres Ticket darf diese Werte hartkodieren.

**Neue Config-Keys:**

| Key                                     | Default | Env                                       |
| --------------------------------------- | ------- | ----------------------------------------- |
| `funnel.lead.default_price`             | 15.00   | `FUNNEL_LEAD_DEFAULT_PRICE`               |
| `funnel.lead.reservation_ttl`           | 10 min  | `FUNNEL_LEAD_RESERVATION_TTL`             |
| `funnel.lead.retention_days`            | 730     | `FUNNEL_LEAD_RETENTION_DAYS`              |
| `funnel.lead.stale_after_days`          | 3       | `FUNNEL_LEAD_STALE_AFTER_DAYS`            |
| `funnel.public.rate_limit_per_hour`     | 20      | `FUNNEL_PUBLIC_RATE_LIMIT_PER_HOUR`       |
| `funnel.public.min_seconds_before_submit` | 30    | `FUNNEL_PUBLIC_MIN_SECONDS_BEFORE_SUBMIT` |
| `funnel.call.answered_after_seconds`    | 30      | `FUNNEL_CALL_ANSWERED_AFTER_SECONDS`      |
| `funnel.call.max_failed_attempts`       | 3       | `FUNNEL_CALL_MAX_FAILED_ATTEMPTS`         |
| `funnel.call.min_gap_hours`             | 2       | `FUNNEL_CALL_MIN_GAP_HOURS`               |
| `funnel.call.min_days`                  | 2       | `FUNNEL_CALL_MIN_DAYS`                    |
| `funnel.call.deadline_days`             | 7       | `FUNNEL_CALL_DEADLINE_DAYS`               |

Fuer `funnel.public.rate_limit_per_hour` gab das Ticket keinen Default vor; 20
Submits pro IP und Stunde sind fuer einen Quiz-Funnel grosszuegig und stoppen
trotzdem einfache Bots.
