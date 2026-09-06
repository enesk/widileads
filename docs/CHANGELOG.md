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

### FB-006 — Sanctum-API-Tokens je Tenant

**Was:** API-Tokens gehören dem Tenant (`Tenant` ist tokenable), nicht einem einzelnen
Nutzer. Abilities als Enum `TenantApiAbility` (`funnels:read`, `funnels:write`,
`leads:read`, `webhooks:manage`). Neue Dashboard-Seite „API-Zugänge" in reinem
Livewire: Token erstellen mit Klartext-Anzeige genau einmal, widerrufen, letzte
Nutzung. Alle `/api/v1/*`-Routen tragen `auth:sanctum` + `tenant.from-token`,
Endpunkte zusätzlich `ability:<...>`.

**Warum:** Ein Token muss den Weggang des Nutzers überleben, der es angelegt hat, und
darf ausschließlich an die Daten seines eigenen Tenants kommen. Der Tenant als
Token-Träger macht diese Isolation zur Eigenschaft des Modells statt zu einer Regel,
die jeder Endpunkt selbst einhalten müsste.

**Neue Config-Keys:** `config/funnel.php` → `api.token_expiration_days` (0 = kein
Ablauf, `FUNNEL_API_TOKEN_EXPIRATION_DAYS`) und `api.max_tokens_per_tenant` (10,
`FUNNEL_API_MAX_TOKENS_PER_TENANT`).

**Migrationen:** keine — `personal_access_tokens` ist bereits polymorph.

Neue Berechtigung `TenancyPermissionConstants::PERMISSION_MANAGE_API_TOKENS`, im
Seeder der Tenant-Rolle `admin` zugewiesen. `GET /api/v1/me` gibt den Tenant des
verwendeten Tokens samt Abilities zurück; `GET /api/v1/ping/leads` ist eine minimale
Sonde für die Ability-Prüfung. Fachliche Endpunkte kommen ab FB-030.

### FB-005 — Audit-Log

**Was:** Neue Tabelle `audit_logs` und der Dienst `App\Services\AuditLogger` halten
sicherheitsrelevante Vorgänge fest. Einträge sind unveränderlich: Model und
Query-Builder werfen bei Änderungs- und Löschversuchen eine
`AuditLogIsImmutableException` (nur `created_at`, kein `updated_at`). Verdrahtet sind
die bereits vorhandenen Pflichtereignisse Login, Mandantenwechsel und Rollenänderung
(Listener in `app/Listeners/Audit`). Die Aktionen für spätere Tickets sind im Enum
`App\Constants\AuditAction` vorbereitet: API-Token erstellt/gelöscht (FB-006),
Datenexport (FB-073), Lead-Kauf (FB-054), Zwangsstatuswechsel (FB-036) — sie müssen dort
nur noch `AuditLogger::log()` aufrufen. Im Admin-Panel gibt es unter „Settings" eine
reine Leseansicht mit Filtern (Vorgang, Mandant, Zeitraum); Anlegen, Bearbeiten und
Löschen sind abgeschaltet.

**Warum:** Für den Nachweis, wer wann welchen sicherheitsrelevanten Vorgang ausgelöst
hat, braucht die Plattform ein fälschungssicheres Protokoll. Die IP-Adresse wird dabei
nie im Klartext gespeichert oder geloggt, sondern nur als mit App-Salt gesalzener
SHA-256-Hash; Payload-Schlüssel wie `password`, `token` oder `ip` werden vor dem
Speichern durch `[redaktiert]` ersetzt.

**Neue Config-Keys:** `config/funnel.php` → `audit.ip_salt` (leer → Fallback `APP_KEY`,
`FUNNEL_AUDIT_IP_SALT`), `audit.redacted_payload_keys` (Liste mit Passwort-, Token- und
IP-Schlüsseln, `FUNNEL_AUDIT_REDACTED_PAYLOAD_KEYS`, kommagetrennt). Zusätzlich steht
`config/permission.php` → `events_enabled` jetzt auf `true`, weil Spatie die Ereignisse
`RoleAttached`/`RoleDetached` nur dann auslöst — ohne sie bliebe die Rollenänderung
unprotokolliert.

**Migrationen:** `2026_09_06_120000_create_audit_logs_table` legt `audit_logs` an
(tenant_id/user_id nullable mit `nullOnDelete`, action, subject_type, subject_id,
payload JSON, ip_hash char(64), created_at) — additiv, mit `down()`.

### FB-002 — Rolle `buyer` und Tenant-Typ

**Was:** `tenants.type` ist ein Enum (`operator` | `buyer`, Default `operator`).
Betreiber-Tenants verwalten Funnels, Käufer-Tenants nutzen den Marktplatz — die
Entscheidung fällt ausschließlich über den Typ. Dazu: Tenant-Rolle `buyer`, die Gates
`funnels.manage` und `marketplace.access`, die Middleware `EnsureTenantType`
(Alias `tenant.type:operator|buyer`) und die Typ-Anzeige im Admin-Panel.

**Warum:** Beide Tenant-Arten teilen sich dieselbe Anwendung, dürfen aber
unterschiedliche Bereiche sehen. Ein einziges Feld als Quelle verhindert, dass die
Sichtbarkeitsregel später an mehreren Stellen unterschiedlich implementiert wird.

**Neue Config-Keys:** keine.

**Migrationen:** `add_type_to_tenants_table` — additiv, `type` mit Default `operator`
und Index; bestehende Tenants bleiben Betreiber. Mit `down()`.

Die Funnel-Verwaltung (FB-E1) und der Marktplatz (FB-E5) existieren noch nicht. Die
Zugriffsregel ist deshalb über Testrouten belegt, die dieselbe Middleware und dieselben
Gates verwenden; die echten Seiten hängen sich in FB-E1/FB-E5 dort ein.

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
