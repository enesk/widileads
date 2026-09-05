# Teil 3 — Epics und Tickets

Prio: **M** = Muss für MVP · **S** = Soll · **K** = Kann (Phase 2)
Größe: S (≤ 0,5 Tag) · M (1–2 Tage) · L (3–5 Tage)

> **Hinweis:** Der Ticketinhalt ist unverändert aus dem Ursprungsdokument übernommen.
> Wo ein Ticket Pest, Laravel 12 / Filament 4 / Livewire 3 oder Larastan Level 6
> erwähnt, gelten die bestätigten Korrekturen aus
> [agent-prompt.md → Abweichungen vom Ursprungsdokument](agent-prompt.md#abweichungen-vom-ursprungsdokument).
> Die betroffenen Stellen sind unten zusätzlich mit einer *Korrektur:*-Zeile markiert.

## FB-E0 — Fundament

| Nr. | Titel | Prio | Größe | Abhängig von |
|---|---|:-:|:-:|---|
| FB-001 | Projekt-Setup auf SaasyKit, ungenutzte Module per Feature-Flag deaktivieren | M | M | — |
| FB-002 | Rolle `buyer` und Tenant-Typ (`operator` / `buyer`) einführen | M | S | FB-001 |
| FB-003 | `DestructiveCommandGuard` (Sperre für migrate:fresh/refresh/reset, db:wipe) | M | S | FB-001 |
| FB-004 | `config/funnel.php` mit allen Schwellwerten anlegen | M | S | FB-001 |
| FB-005 | Audit-Log-Dienst für sicherheitsrelevante Vorgänge | M | M | FB-001 |
| FB-006 | Sanctum-API-Tokens je Tenant, Verwaltung im Tenant-Dashboard | M | M | FB-002 |

**FB-001 — Projekt-Setup**
Frisches SaasyKit-Projekt auf Laravel 12. In `config/saasykit.php` (bzw. äquivalent) Blog, Roadmap, Announcements, Referral deaktivieren; Routen und Navigationseinträge dieser Module dürfen nicht erscheinen. Horizon, Redis, Pint, Larastan (Level 6) und Pest einrichten. CI-Skript `composer check` = pint --test + phpstan + test.
*Akzeptanz:* `/blog`, `/roadmap` liefern 404. `composer check` läuft grün. README mit Setup-Schritten.
*Korrektur:* Das Projekt existiert bereits auf Laravel 13 / Filament 5 / Livewire 4 / PHP 8.4 — es wird konfiguriert, nicht neu aufgesetzt. **Kein Pest**; das Testframework bleibt PHPUnit. `phpstan.neon` steht heute auf Level 3; die **Anhebung auf Larastan Level 6 ist Bestandteil dieses Tickets**.

**FB-002 — Rolle `buyer` und Tenant-Typ**
`tenants.type` Enum (`operator`, `buyer`). Rolle `buyer` in `tenant_user.role`. Policies: buyer-Tenants sehen keine Funnel-Verwaltung, operator-Tenants keinen Marktplatz. Middleware `EnsureTenantType`.
*Akzeptanz:* Feature-Tests für beide Tenant-Typen auf je drei Routen (200 vs. 403).

**FB-003 — DestructiveCommandGuard**
Service Provider, der `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe` in jeder Umgebung abbricht (Exit-Code 1, klare Meldung), auch mit `--force`. Ausnahme nur in Tests via `APP_ENV=testing` + explizitem `FUNNEL_ALLOW_DESTRUCTIVE=1`.
*Akzeptanz:* Test ruft jeden Befehl auf und erwartet Abbruch.

**FB-004 — Konfiguration**
`config/funnel.php`: `lead.default_price` (15.00), `lead.reservation_ttl` (10 min), `lead.retention_days` (730), `lead.stale_after_days` (3), `public.rate_limit_per_hour`, `public.min_seconds_before_submit` (Zeitfalle), `call.answered_after_seconds` (30), `call.max_failed_attempts` (3), `call.min_gap_hours` (2), `call.min_days` (2), `call.deadline_days` (7). Alle mit env-Fallback.
*Akzeptanz:* Kein Ticket ab hier darf diese Werte hartkodieren; Larastan-Regel oder Grep-Test in FB-042.

**FB-005 — Audit-Log**
Tabelle `audit_logs` (tenant_id nullable, user_id, action, subject_type, subject_id, payload JSON, ip_hash, created_at). `AuditLogger::log()`. Pflichtereignisse: Login, Tenant-Wechsel, Rollenänderung, API-Token erstellt/gelöscht, Export, Lead-Kauf, Zwangsstatuswechsel. Read-only-Ansicht in Filament.
*Akzeptanz:* Eintrag ist nach Erstellung nicht änderbar (Model ohne update/delete, Test).

**FB-006 — API-Tokens**
Sanctum-Tokens mit Abilities (`funnels:read`, `funnels:write`, `leads:read`, `webhooks:manage`). Livewire-Seite „API-Zugänge" im Tenant-Dashboard: erstellen (Token einmal anzeigen), widerrufen, letzte Nutzung. Alle `/api/v1/*`-Routen tragen `auth:sanctum` + `tenant.from-token`.
*Akzeptanz:* Token eines Tenants erreicht keine Daten eines anderen Tenants (Test).

---

## FB-E1 — Funnel-Schema und Builder

| Nr. | Titel | Prio | Größe | Abhängig von |
|---|---|:-:|:-:|---|
| FB-010 | Datenmodell Funnel / Steps / Questions / Options / Conditions / Results | M | L | FB-002 |
| FB-011 | Fragetypen und Validierungsregeln als Enum + Strategy-Klassen | M | M | FB-010 |
| FB-012 | Verzweigungslogik (Conditions) mit Priorität und Evaluator | M | M | FB-011 |
| FB-013 | Scoring und Ergebnis-Screens (wie Pfotencheck-Auswertung) | M | M | FB-012 |
| FB-014 | Funnel-Versionierung: Publish erzeugt unveränderlichen JSON-Snapshot | M | M | FB-013 |
| FB-015 | Livewire-Builder: Schritte und Fragen per Drag-and-drop | M | L | FB-011 |
| FB-016 | Livewire-Builder: Conditions- und Results-Editor | S | L | FB-012, FB-015 |
| FB-017 | Theme-Editor (Farben, Logo, Schrift, Fortschrittsanzeige, Button-Texte) | S | M | FB-015 |
| FB-018 | Funnel duplizieren, archivieren, Vorschau-Link (signiert, befristet) | S | S | FB-014 |
| FB-019 | Funnel-Vorlagen: „Pfotencheck" als importierbare Seed-Vorlage | S | M | FB-014 |

**FB-010 — Datenmodell**
Tabellen gemäß [Teil 2](datenmodell.md). `funnels`: tenant_id, public_token (ULID, unique), name, slug, status (`draft|published|archived`), lead_price (decimal 8,2, nullable → Fallback config), contact_step_position, settings JSON. `funnel_steps`: funnel_id, position, title, description. `funnel_questions`: step_id, position, type, field_key (lowercase, unique je Funnel), label, help_text, required, validation JSON, meta JSON. `funnel_options`: question_id, position, label, value, score (int, nullable), image_path. Reservierte Feldschlüssel: `vorname`, `nachname`, `name`, `email`, `telefon`, `plz`, `einwilligung`. Factories + Seeder.
*Akzeptanz:* Unique-Constraint (funnel_id, field_key) greift; Feldschlüssel wird beim Speichern normalisiert (Test „E-Mail" → „e_mail").

**FB-011 — Fragetypen**
Enum `QuestionType`: `single_choice`, `multi_choice`, `text`, `textarea`, `number`, `email`, `phone`, `date`, `postal_code`, `image_choice`, `slider`, `consent`, `info` (reiner Textschritt). Je Typ eine Klasse `app/Funnel/QuestionTypes/*` mit `rules()`, `normalize()`, `render()` (Blade-Komponentenname). `phone` normalisiert nach E.164 (libphonenumber), `postal_code` validiert 5-stellig DE.
*Akzeptanz:* Unit-Test je Typ für gültige/ungültige Eingaben.

**FB-012 — Verzweigungslogik**
`funnel_conditions`: funnel_id, source_question_id, operator (`equals|not_equals|in|gt|lt|contains|answered|score_gte`), value JSON, target_step_id, priority. `StepResolver::next(FunnelVersion $v, array $answers, int $currentStep)`: höchste Priorität gewinnt, ohne Treffer → nächster Schritt in Reihenfolge. Zyklenschutz (max. Schrittanzahl × 2).
*Akzeptanz:* Unit-Tests ohne DB gegen JSON-Snapshot; Test für Zyklus.

**FB-013 — Scoring und Ergebnisse**
Jede Option kann `score` tragen. `funnel_results`: funnel_id, min_score, max_score, title, body (Markdown), cta_label, cta_url, show_contact_form (bool). `ResultResolver` liefert das passende Ergebnis. Beispiel Pfotencheck: 0–3 „geringes Risiko", 4–7 „erhöhtes Risiko — Vorsorge empfohlen", 8+ „hohes Risiko". Ergebnis wird am Lead gespeichert (`leads.result_id`, `leads.score`).
*Akzeptanz:* Lückenlose, überschneidungsfreie Score-Bereiche werden beim Publish validiert.

**FB-014 — Versionierung**
`funnel_versions`: funnel_id, version (int), snapshot JSON (komplette Struktur inkl. Theme), published_at, published_by. Publish ist eine Action `PublishFunnel`, die validiert (mind. 1 Schritt, Kontaktfelder vorhanden, Results lückenlos), Snapshot schreibt, `funnels.status = published`, `current_version_id` setzt. Öffentliche Auslieferung liest **nur** Snapshots, nie die Live-Tabellen. Leads referenzieren `funnel_version_id`.
*Akzeptanz:* Nach Publish geänderter Draft ändert die öffentliche Strecke nicht (Test).

**FB-015 — Builder UI**
Livewire-Komponente `FunnelBuilder`: linke Spalte Schritte (sortierbar via Alpine + SortableJS), Mitte Fragen des Schritts (sortierbar), rechts Eigenschaften der markierten Frage. Autosave mit Debounce, Konfliktschutz über `updated_at`-Vergleich. Keine Filament-Nutzung im Tenant-Dashboard.
*Akzeptanz:* Browser-Test (Dusk oder Livewire-Test) für Anlegen, Umsortieren, Löschen mit Bestätigung.

**FB-016 — Conditions/Results-Editor**
Regel-Editor: „Wenn [Frage] [Operator] [Wert] → springe zu [Schritt]" mit Priorität. Results-Editor mit Score-Bereichsvorschau (Balken). Live-Validierung der Lücken.

**FB-017 — Theme-Editor**
`funnel_themes`: primary/secondary/bg/text color, font (Whitelist: System, Inter, Roboto, Open Sans), logo_path, progress_style (`bar|steps|none`), button_next/back/submit-Texte, border_radius. Live-Vorschau im iFrame.

**FB-018 — Duplizieren, Archivieren, Vorschau**
Action `DuplicateFunnel` (tiefe Kopie, neuer Token, Status draft). Archivierte Funnels liefern öffentlich eine konfigurierbare „Nicht mehr verfügbar"-Seite. Vorschau: `URL::temporarySignedRoute('funnel.preview', 30 min)`.

**FB-019 — Vorlage „Pfotencheck"**
Seed-Vorlage `database/templates/pfotencheck.json` (Version des Snapshot-Formats): Schritte Tierart (Hund/Katze/Sonstige, image_choice) → Alter → Rasse/Größe → Vorerkrankungen (multi) → Verhalten/Aktivität → aktueller Versicherungsstatus → Ergebnis → Kontakt (vorname, nachname, email, telefon, plz, einwilligung). Scores und drei Ergebnisbereiche. Import über Filament-Action „Vorlage anlegen".
*Akzeptanz:* Import erzeugt publizierbaren Funnel ohne Validierungsfehler.

---

## FB-E2 — Öffentliche Auslieferung und Einbettung

| Nr. | Titel | Prio | Größe | Abhängig von |
|---|---|:-:|:-:|---|
| FB-020 | Öffentliche Runtime `/f/{token}`: Schritt-für-Schritt-Rendering aus Snapshot | M | L | FB-014 |
| FB-021 | Session- und Ereignisprotokoll (Aufruf, Schritt, Abbruch, Absenden), Teilfortschritt | M | M | FB-020 |
| FB-022 | Herkunftserfassung: UTM, Referrer, Origin, IP-Hash | M | S | FB-021 |
| FB-023 | Spam-Abwehr: Zeitfalle, Honeypot, Rate-Limit je IP-Hash, Dublettenprüfung | M | M | FB-021 |
| FB-024 | JS-Embed-Snippet (`<script data-funnel="…">`) mit iFrame + Auto-Resize | M | M | FB-020 |
| FB-025 | CORS-Allowlist je Funnel (`funnel_origins`) | M | S | FB-024 |
| FB-026 | Headless-Runtime-API `/api/public/v1/funnels/{token}` für eigene Frontends | S | M | FB-020 |
| FB-027 | Barrierefreiheit WCAG 2.1 AA, Mobile-first ab 360 px, Touch-Ziele 44 px | S | M | FB-020 |

**FB-020 — Runtime**
Livewire-Komponente `FunnelRunner` (eigenes minimales Layout ohne SaasyKit-Navigation). Lädt Snapshot, hält Antworten in einer `public_session` (ULID im Cookie/LocalStorage), validiert je Schritt mit den Regeln aus FB-011, fragt `StepResolver` nach dem nächsten Schritt, zeigt Result-Screen, dann Kontaktschritt. Absenden erzeugt Lead über `CreateLeadFromSession` (FB-031). Schrittwechsel < 300 ms serverseitig.
*Akzeptanz:* Kompletter Durchlauf des Pfotencheck-Seeds im Feature-Test; erratener Token → 404.

**FB-021 — Sessions und Ereignisse**
`public_sessions`: funnel_version_id, token, answers JSON (Teilfortschritt), current_step, started_at, completed_at, abandoned_at. `session_events`: session_id, type (`view|step_view|step_complete|submit|abandon`), step_position, created_at. Scheduler markiert Sessions nach 30 min Inaktivität als abandoned.

**FB-022 — Herkunft**
utm_source/medium/campaign/term/content, referrer, embed_origin, ip_hash (SHA-256 mit App-Salt), user_agent (gekürzt). Niemals IP im Klartext.
*Akzeptanz:* Grep-Test: keine Spalte/Log-Zeile enthält Roh-IP.

**FB-023 — Spam-Abwehr**
Honeypot-Feld (CSS-versteckt), Zeitfalle (`min_seconds_before_submit`), Rate-Limit je ip_hash (`rate_limit_per_hour`), Dublettenprüfung: gleiche E-Mail oder Telefon innerhalb 30 Tagen im selben Funnel → Lead entsteht mit `lead_state = neu` und Flag `duplicate_of_lead_id`, Prüfjob entscheidet (FB-033). Kein Captcha im Standardpfad.

**FB-024 — Embed-Snippet**
`public/embed.js` (vanilla, < 5 kB, kein Framework): liest `data-funnel`, erzeugt iFrame auf `/f/{token}?embed=1&origin=…`, postMessage-Resize, optionaler Overlay-Modus (`data-mode="overlay"`) mit Trigger-Button. Abwärtskompatibel versioniert (`/embed/v1.js`).
*Akzeptanz:* Testseite `resources/views/dev/embed-test.blade.php` funktioniert lokal.

**FB-025 — CORS-Allowlist**
`funnel_origins`: funnel_id, origin. Embed und Public-API akzeptieren nur gelistete Origins (plus eigene Domain). Verstoß → 403 + Audit-Eintrag. Ursprung wird am Lead gespeichert.

**FB-026 — Headless-API**
GET Snapshot (ohne interne IDs), POST `sessions`, PATCH `sessions/{id}/answers` (validiert, gibt nächsten Schritt zurück), POST `sessions/{id}/submit`. Gleiche Spam-Regeln wie FB-023.

---

## FB-E3 — Management-API (REST v1)

| Nr. | Titel | Prio | Größe | Abhängig von |
|---|---|:-:|:-:|---|
| FB-030a | OpenAPI-3.1-Spezifikation als Single Source of Truth (`docs/openapi.yaml`) | M | M | FB-006 |
| FB-030b | CRUD `/api/v1/funnels` inkl. verschachtelter Steps/Questions/Options/Conditions/Results | M | L | FB-014 |
| FB-030c | `POST /api/v1/funnels/{id}/publish`, `/duplicate`, `/archive`, `GET /versions` | M | S | FB-030b |
| FB-030d | `GET /api/v1/leads` (gefiltert, paginiert, maskiert nach Rolle), `GET /leads/{id}` | M | M | FB-031 |
| FB-030e | Webhooks: `funnel_webhooks` (URL, Secret, Events), signierte Zustellung, Retry mit Backoff | M | M | FB-031 |
| FB-030f | API-Ratenbegrenzung je Token, Idempotency-Key für POST | S | S | FB-030b |

**FB-030a — OpenAPI**
Spezifikation zuerst, Code danach. Generierte Doku unter `/docs/api` (Scalar oder Swagger UI). Contract-Test prüft, dass jede Route der Spec existiert und jede Response dem Schema entspricht (spectator oder eigenes Assertion-Set).

**FB-030b — Funnel-CRUD**
Ein `PUT /funnels/{id}/structure` nimmt die komplette Struktur als JSON entgegen (gleiches Format wie Snapshot/Vorlage) und schreibt sie transaktional in die Live-Tabellen — so kann ein externes System einen kompletten Funnel in einem Aufruf anlegen. Zusätzlich feingranulare Endpunkte je Ressource. Validierung über Form Requests, Fehler nach RFC 9457 (Problem Details).

**FB-030e — Webhooks**
Events: `lead.created`, `lead.state_changed`, `lead.purchased`, `funnel.published`. Payload signiert (HMAC-SHA256 im Header `X-Funnel-Signature`, Zeitstempel gegen Replay). Zustellung als Queue-Job, 5 Versuche mit exponentiellem Backoff, Zustellprotokoll `webhook_deliveries` (status, response_code, attempts). Kontaktdaten nur in Webhooks an den **Eigentümer**-Tenant, nie an buyer-Webhooks vor Kauf.
*Akzeptanz:* Test mit manipulierter Signatur auf Empfängerseite dokumentiert; Beispiel-Verifikation in PHP/Node in docs.

---

## FB-E4 — Lead-Kern

| Nr. | Titel | Prio | Größe | Abhängig von |
|---|---|:-:|:-:|---|
| FB-030 | `LeadState` Enum, `LeadTransitions` Tabelle, `LeadStateService`, `lead_state_log` | M | M | FB-004 |
| FB-031 | `CreateLeadFromSession`: Lead + lead_answers aus Session, Kontakt-Extraktion, Preis-Snapshot | M | M | FB-030, FB-021 |
| FB-032 | `LeadContact` Value Object: Auflösung reservierter Feldschlüssel + serverseitige Maskierung | M | M | FB-031 |
| FB-033 | Prüfjob `neu → verfuegbar`: Dubletten, Plausibilität (Telefon E.164, Wegwerf-Mail-Liste), Scoring-Threshold | M | M | FB-031, FB-023 |
| FB-034 | Lead-Detail und Lead-Liste im Operator-Dashboard (Filter, Suche, Sortierung, Liegenbleiber-Markierung) | M | L | FB-032 |
| FB-035 | Notizen je Lead (append-only) | S | S | FB-034 |
| FB-036 | Manuelle Statussetzung durch Operator-Admin mit Pflichtbegründung (nur erlaubte Übergänge) | M | S | FB-030 |
| FB-037 | Aufbewahrung: täglicher Job anonymisiert Leads nach `retention_days`, `abgelaufen` für nie verkaufte | M | M | FB-030 |
| FB-038 | DSGVO: Auskunft (Export je E-Mail) und Löschersuchen (Anonymisierung, protokolliert) | M | M | FB-037 |

**FB-030 — Zustandsmaschine**
Enum `LeadState` (Werte aus [Teil 2](datenmodell.md)). `LeadTransitions::TABLE` als `array<string, list<string>>`. `LeadStateService::transition(Lead $lead, LeadState $to, LeadTransitionReason $reason, ?User $actor, array $meta = [])`: prüft Erlaubnis, schreibt in Transaktion `leads.lead_state` + `lead_state_log` (from, to, reason, actor_id, meta JSON, created_at), feuert `LeadStateChanged`-Event. Endzustände schreiben einmalig `settled_price`/`settled_at`. `lead_state` in `$guarded`.
*Akzeptanz:* Unit-Tests jedes erlaubten und mindestens zehn verbotener Übergänge; Log-Eintrag nicht änderbar.

**FB-031 — Lead anlegen**
Aus abgeschlossener Session: `leads` (tenant_id = Funnel-Eigentümer, funnel_id, funnel_version_id, public_session_id, lead_state = neu, score, result_id, price_at_creation aus Funnel bzw. Config, origin, utm-Felder, phone_e164, email_normalized). `lead_answers` 1:1 aus Session-Antworten (field_key, value JSON). Event `LeadCreated`. Idempotent je Session.

**FB-032 — Kontakt und Maskierung**
`LeadContact::fromLead()`: löst vorname/nachname/name/email/telefon/plz auf. `masked()`: E-Mail `a…@example.com`, Telefon `+49 30 …`, Name Klartext, PLZ auf 2 Stellen (`76…`) gekürzt. Anwendung in **einer** Stelle: `LeadResource` (API) und `LeadPresenter` (Views) rufen `contactFor(User $viewer)` auf, das je nach Rolle/Kaufstatus maskiert oder nicht.
*Akzeptanz:* Test rendert Lead-Detail als buyer ohne Kauf und asserted, dass Klartext-E-Mail/Telefon **im gesamten HTML** nicht vorkommen.

**FB-033 — Prüfjob**
Queue-Job nach `LeadCreated`: Dublette (FB-023) → `ungueltig` mit Grund `duplicate`; Telefon nicht normalisierbar UND E-Mail auf Wegwerf-Liste → `ungueltig`; sonst → `verfuegbar`. Alle Regeln in `config/funnel.php` schaltbar.

**FB-034 — Lead-Liste**
Livewire-Tabelle: Filter nach Funnel, Zustand, Zeitraum, PLZ-Präfix, Score-Bereich; Volltext über Name/E-Mail/Telefon (Verwalter) bzw. Name (member); Liegenbleiber (`verfuegbar` > `stale_after_days`) hervorgehoben. < 500 ms bei 50.000 Leads (Index auf tenant_id, lead_state, created_at; Test mit Seed).

**FB-036 — Manuelle Statussetzung**
Nur Operator-Admins, nur Übergänge aus `LeadTransitions`, Pflichtbegründung ≥ 10 Zeichen, Audit-Eintrag. Leads mit vorhandenen `call_attempts` (Phase 2) sind ausgenommen — Abweisung mit Fachausnahme.

**FB-037 — Aufbewahrung**
Job: Leads in Endzustand älter als `retention_days` → Anonymisierung (`lead_answers` mit Personenbezug überschrieben, `phone_e164`/`email_normalized` null, `anonymized_at` gesetzt); Leads `verfuegbar` älter als `retention_days` → `abgelaufen` (danach Anonymisierung). Zähl- und Preisdaten bleiben.

---

## FB-E5 — Lead-Marktplatz für Versicherungsagenturen

| Nr. | Titel | Prio | Größe | Abhängig von |
|---|---|:-:|:-:|---|
| FB-050 | Buyer-Onboarding: Registrierung als buyer-Tenant, Freischaltung durch Plattform-Admin | M | M | FB-002 |
| FB-051 | `buyer_profiles`: Kaufkriterien (Funnels, PLZ-Bereiche, Tierarten/Antwortfilter, Tageslimit, Autokauf an/aus) | M | M | FB-050 |
| FB-052 | Guthaben: SaasyKit-Plans als Credit-Pakete (z. B. 10/50/200 Leads), `credit_ledger` append-only | M | L | FB-050 |
| FB-053 | Marktplatz-Ansicht: verfügbare Leads maskiert, gefiltert nach Kriterien, Sortierung, Merkliste | M | L | FB-032, FB-051 |
| FB-054 | Kaufvorgang: Reservierung (TTL), Guthabenprüfung, Kauf in Transaktion, Freigabe Klartext, `verkauft` | M | L | FB-052, FB-053 |
| FB-055 | Exklusiv- vs. Mehrfachverkauf je Funnel (`max_buyers`, Preis je Modus) | S | M | FB-054 |
| FB-056 | Autokauf: Job kauft neue passende Leads gemäß Profil bis Tageslimit, Benachrichtigung per Mail | S | M | FB-054 |
| FB-057 | „Meine Leads" für Käufer: Liste, Detail, Export CSV, Statusrückmeldung (Interesse/kein Interesse) | M | M | FB-054 |
| FB-058 | Reklamation ohne Anrufnachweis (Übergangslösung): Käufer meldet `unerreichbar`/`ungueltig`, Operator prüft und gibt Gutschrift | M | M | FB-054 |
| FB-059 | Abrechnung: Monatsübersicht je Käufer (verkauft, erreicht, unerreichbar, ungültig, Summe), CSV; Rechnung auf Guthaben via SaasyKit-Invoice | M | M | FB-052 |
| FB-060 | Operator-Sicht auf Käufer: Umsatz, Reklamationsquote, Auffälligkeiten (> 20 Prozentpunkte über Durchschnitt) | S | M | FB-059 |

**FB-050 — Buyer-Onboarding**
Registrierungsformular (Firma, Ansprechpartner, Vermittlerregister-Nr. optional, Ust-IdNr.), erzeugt buyer-Tenant im Status `pending`. Plattform-Admin schaltet in Filament frei (`active`) oder lehnt ab. Vor Freischaltung kein Marktplatz-Zugriff. AV-Vertrag-Bestätigung als Pflicht-Checkbox, Zeitstempel gespeichert.

**FB-051 — Kaufkriterien**
`buyer_profiles`: tenant_id, funnel_ids JSON, postal_prefixes JSON (z. B. `["76","77","68"]`), answer_filters JSON (field_key → erlaubte Werte, z. B. `tierart: [hund, katze]`), min_score, daily_limit, auto_buy (bool), notify_email. `LeadMatcher::matches(Lead, BuyerProfile)` als reine Funktion.
*Akzeptanz:* Unit-Tests für jeden Filtertyp.

**FB-052 — Guthaben**
SaasyKit-Plans als Einmalkauf-Produkte (One-time purchase) mit Metadatum `credits`. Nach erfolgreichem Stripe-Webhook: Buchung in `credit_ledger` (tenant_id, type `purchase|debit|refund|adjustment`, credits, amount_cents, reference_type/id, created_at). Saldo = SUM, gecacht je Tenant. Alternativ „auf Rechnung": Admin bucht `adjustment` manuell. Ledger ist append-only (kein update/delete).
*Akzeptanz:* Saldo nie negativ (DB-seitige Prüfung in Kauf-Transaktion, Test mit parallelen Käufen).

**FB-053 — Marktplatz**
Livewire-Liste für buyer: nur Leads `verfuegbar`, die `LeadMatcher` passiert haben; Anzeige maskiert (FB-032) + Qualifizierungsdaten (Tierart, Alter, Region, Score, Ergebnis, Erstellzeit). Sortierung neueste/Score. Leads, die ein anderer Käufer reserviert hat, erscheinen als „vergriffen".

**FB-054 — Kaufvorgang**
`PurchaseLead` Action: 1) `transition(reserviert)` mit `reserved_by`/`reserved_until` — `SELECT … FOR UPDATE`, bei parallelem Klick gewinnt genau einer; 2) Guthabenprüfung; 3) `lead_purchases` (lead_id, buyer_tenant_id, price_cents, purchased_at) + `credit_ledger` debit + `transition(verkauft)` in **einer** Transaktion; 4) Event `LeadPurchased` → Webhook, Mail an Käufer mit Klartext-Kontakt. Reservierung verfällt nach TTL via Scheduler → zurück auf `verfuegbar`.
*Akzeptanz:* Concurrency-Test (zwei Prozesse) → genau ein Kauf; Rollback-Test bei Guthabenmangel.

**FB-055 — Exklusiv/Mehrfach**
`funnels.sale_mode` (`exclusive|shared`), `funnels.max_buyers` (Standard 1), `funnels.shared_price`. Bei shared bleibt der Lead `verfuegbar`, bis `max_buyers` erreicht; Käufer sehen, wie viele bereits gekauft haben.

**FB-058 — Reklamation (Übergangslösung bis Phase 2)**
Käufer kann binnen 7 Tagen `unerreichbar` oder `ungueltig` beantragen (Grund Pflichtfeld). Antrag geht an Operator-Queue; Operator bestätigt → `transition()` + `credit_ledger` refund; lehnt ab → Lead bleibt `verkauft`, nach Frist automatisch `erreicht`. Reklamationsquote je Käufer wird berechnet (FB-060). **Sobald FB-E7 produktiv ist, wird dieser Pfad für Leads mit Anrufprotokoll gesperrt.**

---

## FB-E6 — Auswertung und Export

| Nr. | Titel | Prio | Größe | Abhängig von |
|---|---|:-:|:-:|---|
| FB-070 | Trichter je Funnel: Aufrufe, Abschlüsse je Schritt, Abbruchpunkte, Abschlussquote (DB-Aggregate) | M | M | FB-021 |
| FB-071 | Zeitverlauf: Leads je Tag/Woche/Monat nach Funnel, Herkunft, Zustand | S | M | FB-031 |
| FB-072 | Umsatzübersicht Operator: verkaufte Leads, Umsatz, Gutschriften, je Funnel und Käufer | M | M | FB-059 |
| FB-073 | Export Leads/Anfragen als CSV/XLSX, wählbare Spalten, asynchron, signierter befristeter Download-Link | M | M | FB-034 |
| FB-074 | Plattform-Admin-Dashboard (Filament): Mandanten, Käufer-Freischaltungen, Reklamationen, Audit-Log, Erreichbarkeit je Käufer | M | L | FB-060 |

---

## FB-E7 — Anrufnachweis (Phase 2, Übernahme aus LP-CALL-Epic)

Fachlich identisch zum vorhandenen Pflichtenheft Abschnitt 4.7. Nur der Anschluss ändert sich: Bewertete Anrufversuche treiben `lead_state` von `verkauft` nach `erreicht` oder `unerreichbar`; FB-058 wird für Leads mit `call_attempts` gesperrt.

| Nr. | Titel | Prio | Größe | Abhängig von |
|---|---|:-:|:-:|---|
| FB-080 | Rufnummernbestätigung des Käufer-Mitarbeiters (TOTP-Anruf), eine Nummer je Benutzer | K | M | FB-057 |
| FB-081 | Click-to-Call über Twilio: erst Mitarbeiter, dann Lead mit Mitarbeiter-Nummer als Caller-ID | K | L | FB-080 |
| FB-082 | `call_attempts` mit `raw_payload`, Signaturprüfung aller Webhooks (403 ohne Datensatz) | K | M | FB-081 |
| FB-083 | Bewertung eines Versuchs an genau einer Stelle; Auflösung nach Regelwerk aus Config | K | M | FB-082 |
| FB-084 | Nachweisstand-Anzeige für Käufer, Fristen-Erinnerung 24 h vorher (eine gebündelte Mail) | K | M | FB-083 |
| FB-085 | Ab Stichtag: Rufnummer des Leads nirgends mehr im ausgelieferten HTML/JSON | K | S | FB-081 |

---

## FB-E8 — Qualität, Sicherheit, Betrieb

| Nr. | Titel | Prio | Größe | Abhängig von |
|---|---|:-:|:-:|---|
| FB-040 | Testdeterminismus: `RefreshDatabase`/Transaktionen korrekt, dreimaliger Lauf identisch | M | S | FB-001 |
| FB-041 | N+1-Schutz: `Model::preventLazyLoading()` im Testing, Query-Count-Assertions auf Listen | M | S | FB-034 |
| FB-042 | Architektur-Tests (Pest Arch): kein `lead_state`-Update außerhalb `LeadStateService`, keine Config-Werte im Code, keine Klartext-Kontaktausgabe außer über `LeadContact` | M | M | FB-030, FB-032 |
| FB-043 | Sicherheits-Checkliste: Mandantentrennung (Cross-Tenant-Tests je Ressource), signierte Links, Rate-Limits, CSP-Header für `/f/*` | M | M | alle E2–E5 |
| FB-044 | Betriebshandbuch: Deployment, Horizon, Backups (< 4 h Wiederherstellung), Runbook für Stripe-Webhook-Ausfall | M | M | FB-052 |
| FB-045 | Seed-Skript „Demo-Umgebung": 1 Operator, 2 Käufer, Pfotencheck-Funnel, 200 generierte Leads in allen Zuständen | S | S | FB-019, FB-054 |

**FB-042 — Architektur-Tests**
*Korrektur:* Es kommt **kein Pest Arch** zum Einsatz. Die drei Regeln — kein `lead_state`-Update außerhalb `LeadStateService`, keine Config-Werte im Code, keine Klartext-Kontaktausgabe außer über `LeadContact` — werden als **normale PHPUnit-Tests** umgesetzt (z. B. über Reflection- und Grep-basierte Assertions über `app/`). Umfang und Akzeptanz bleiben unverändert.
