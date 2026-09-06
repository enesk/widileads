# Changelog

> **Neue Einträge gehören nicht mehr hierher.** Jedes Ticket legt seinen Eintrag als
> eigene Datei unter [`docs/changelog.d/`](changelog.d/) ab — eine Datei je Ticket
> (`FB-###.md`). Diese Sammeldatei hat bei parallel laufenden Tickets zwangsläufig
> Merge-Konflikte erzeugt, weil jeder Eintrag oben eingefügt wurde. Die vorhandenen
> Einträge bleiben als Archiv stehen.

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

### FB-036 — Manuelle Statussetzung durch Operator-Admins

**Was:** `App\Services\ManualLeadStateService::force()` setzt den Zustand eines Leads von
Hand — als Notausgang, wenn der fachliche Ablauf einen Lead nicht dorthin bringt, wo er
hingehört. Der Dienst umgeht dabei nichts: er prüft das Gate `leads.force-state`,
verlangt eine Begründung von mindestens
`config('funnel.lead.manual_state_min_justification_length')` Zeichen (führende und
folgende Leerzeichen zählen nicht mit) und schreibt über
`LeadStateService::transition()` — erlaubt ist also weiterhin nur, was in
`LeadTransitions::TABLE` steht. Der Grund im `lead_state_log` ist `manual_override`, die
Begründung liegt dort als `meta.justification`. Zusätzlich schreibt jeder Vorgang einen
Audit-Eintrag mit der bereits in FB-005 vorbereiteten Aktion
`AuditAction::LEAD_STATE_FORCED` (`from`, `to`, `justification`).

Als Oberfläche gibt es eine schlanke, lesende `LeadResource` im Filament-Admin-Panel mit
genau einer Aktion („Status setzen"). Sie bietet nur Zustände an, die vom aktuellen
Zustand aus erreichbar sind, und verschwindet, sobald es keine gibt (Endzustand). Anlegen,
Bearbeiten und Löschen sind abgeschaltet.

**Warum:** Ein Zwangsstatuswechsel ist ein Eingriff eines Menschen in einen automatischen
Ablauf. Er ist nur dann vertretbar, wenn hinterher nachvollziehbar ist, wer ihn wann und
warum vorgenommen hat — deshalb Pflichtbegründung, Protokolleintrag und Audit-Eintrag,
und deshalb keine Abkürzung an der Zustandsmaschine vorbei. Die Prüfung in der
Oberfläche ist Bequemlichkeit; die Absicherung sitzt im Dienst.

**Neue Config-Keys:** `config/funnel.php` → `lead.manual_state_min_justification_length`
(10, `FUNNEL_LEAD_MANUAL_STATE_MIN_JUSTIFICATION`).

**Migrationen:** keine.

Zwei bewusste Entscheidungen:

- **Die Oberfläche liegt im Admin-Panel, nicht im Tenant-Dashboard.** Die Lead-Liste des
  Betreibers gehört zu FB-034 und hängt über FB-032 an FB-031; hier wird ihr nichts
  vorweggenommen. Das Gate `leads.force-state` erlaubt deshalb heute Plattform-Admins —
  nur die kommen überhaupt an Leads heran. Sobald FB-034 die Dashboard-Ansicht baut,
  kommt der Admin des besitzenden Betreiber-Mandanten im Gate dazu; der Dienst muss dafür
  nicht angefasst werden.
- **Die Ausnahme für Leads mit `call_attempts` entfällt ersatzlos.** Das Ticket nimmt
  Leads mit Anrufprotokoll von der manuellen Statussetzung aus, aber `call_attempts`
  gehört zu FB-E7, und FB-E7 wird nicht gebaut. Es gibt also nichts zu prüfen — wer die
  Regel im Ticket liest, findet sie hier bewusst nicht wieder.

### FB-012 — Verzweigungslogik mit Priorität und Evaluator

**Was:** `App\Funnel\Conditions\StepResolver` bestimmt den nächsten Schritt einer
Funnel-Strecke: Unter den Regeln des aktuellen Schritts gewinnt die mit der höchsten
Priorität, deren Bedingung zutrifft; greift keine, folgt der nächste Schritt in der
gepflegten Reihenfolge. `path()` liefert den kompletten Weg und bricht mit
`FunnelStepCycleException` ab, wenn die Regeln im Kreis führen. Je Operator gibt es
eine Auswertungsklasse in `app/Funnel/Conditions/Comparisons`, aufgelöst über die
`ConditionComparisonRegistry` nach Namenskonvention (`score_gte` →
`ScoreGteComparison`) — wie schon bei den Fragetypen aus FB-011.

**Warum:** Ein falsch ausgewerteter Operator schickt den Endkunden in den falschen
Schritt, und der Lead entsteht mit den falschen Angaben. Die Auswertung vergleicht
deshalb bewusst tolerant: `"7"` und `7` sind dieselbe Antwort, `"Hund"` und `"hund"`
auch — ein Funnel-Ersteller pflegt Werte von Hand, ein Browser liefert alles als
String. Eine Regel, die auf einen nicht vorhandenen Schritt zeigt, wird übersprungen
statt den Endkunden ins Leere zu schicken.

**Snapshot statt Datenbank:** Der Resolver arbeitet gegen die Value Objects in
`app/Funnel/Snapshots` (`FunnelSnapshot::fromArray()`), nicht gegen Eloquent — er läuft
damit ohne Datenbank, und FB-014 kann ihn unverändert mit echten Snapshots aus
`funnel_versions` füttern. Das Format ist in
[snapshot-format.md](funnel-builder/snapshot-format.md) festgehalten; adressiert wird
über Schrittposition und Feldschlüssel, nie über IDs.

**Neue Config-Keys:** `config/funnel.php` → `runtime.max_step_visit_factor` (2,
`FUNNEL_RUNTIME_MAX_STEP_VISIT_FACTOR`) — Zyklenschutz: höchstens Schrittanzahl mal
Faktor.

**Migrationen:** keine.

`FunnelVersion` gibt es noch nicht (FB-014); die Ticket-Signatur
`next(FunnelVersion $v, …)` ist deshalb als `next(FunnelSnapshot $snapshot, …)`
umgesetzt. Scoring bleibt FB-013: `score_gte` liest die Punktzahl aus dem Kontext,
berechnet wird sie dort.

### FB-037 — Aufbewahrung und Anonymisierung

**Was:** Ein täglicher Lauf (`app:apply-lead-retention`, Uhrzeit aus der Konfiguration)
räumt Leads auf, deren Aufbewahrungsfrist abgelaufen ist. Er besteht aus zwei Schritten:
nie verkaufte Leads im Zustand `verfuegbar`, die älter als
`config('funnel.lead.retention_days')` sind, gehen nach `abgelaufen`; danach verlieren
alle Leads in einem Endzustand jenseits der Frist ihren Personenbezug und bekommen
`anonymized_at` gesetzt. Der Zustandswechsel läuft ausschließlich über
`LeadStateService::transition()` mit dem Grund `retention_elapsed` und hinterlässt
denselben Protokolleintrag wie jeder andere Wechsel. Die Anonymisierung selbst steckt in
`App\Services\LeadAnonymizer` — der einen Stelle, an der ein Lead seinen Personenbezug
verliert; `settled_price`, `settled_at`, `lead_state` und `created_at` bleiben
unangetastet. Der Lauf ist wiederholbar: ein zweiter Durchgang am selben Tag ändert
nichts mehr.

**Warum:** Personenbezogene Daten dürfen nicht länger vorgehalten werden als nötig, aber
Umsatz- und Zähldaten der Vergangenheit müssen stimmig bleiben. Beides gleichzeitig geht
nur, wenn Anonymisierung und Löschung getrennte Dinge sind: der Lead bleibt als Zeile
mit seinem Preis und seinem Zustand erhalten, nur die Person dahinter verschwindet. Dass
das Ablaufen über die Zustandsmaschine läuft und nicht per `update()`, ist kein
Formalismus — nur so ist später nachweisbar, wann und warum ein Lead nicht mehr
verkäuflich war.

**Neue Config-Keys:** `config/funnel.php` → `lead.retention_run_at` (`03:15`,
`FUNNEL_LEAD_RETENTION_RUN_AT`) und `lead.retention_chunk_size` (500,
`FUNNEL_LEAD_RETENTION_CHUNK_SIZE`). Die Frist selbst ist der bestehende Wert
`lead.retention_days` (730) aus FB-004.

**Migrationen:** `2026_09_06_150000_add_anonymized_at_to_leads_table` ergänzt
`leads.anonymized_at` samt Index `(anonymized_at, created_at)` — additiv, mit `down()`.

Zwei bewusste Entscheidungen:

- **Gerechnet wird ab `created_at`**, nicht ab Verkauf oder Eintritt in den Endzustand.
  Die Frist hängt am Zeitpunkt der Erhebung der Daten, nicht an ihrer Verwertung.
- **Die von FB-031 noch nicht angelegten Felder** stehen bereits in
  `LeadAnonymizer::PERSONAL_COLUMNS` (`phone_e164`, `email_normalized`) und werden
  übersprungen, solange die Spalten fehlen — der Lauf bricht daran nicht, und sobald
  FB-031 sie anlegt, werden sie ohne Änderung am Code geleert. Das Überschreiben der
  Antworten (`lead_answers`) gehört ebenfalls in diese Klasse, wird aber bewusst nicht
  auf Verdacht vorweggenommen: FB-031 kennt die endgültige Form der Tabelle, dieses
  Ticket nicht. `LeadAnonymizer` ist auch der Einstieg für das Löschersuchen aus FB-038.

### FB-011 — Fragetypen und Validierungsregeln

**Was:** Enum `QuestionType` mit den dreizehn Fragetypen und je Typ eine Handler-Klasse
in `app/Funnel/QuestionTypes` mit `rules()`, `normalize()`, `render()` und
`expectsAnswer()`. Aufgelöst wird über die `QuestionTypeRegistry` nach
Namenskonvention (`single_choice` → `SingleChoiceType`) — es gibt bewusst keine
Zuordnungstabelle und keinen `match`-Block, den ein neuer Typ anfassen müsste.
`funnel_questions.type` trägt jetzt den Enum-Cast. Telefonnummern werden über
libphonenumber nach E.164 normalisiert, Postleitzahlen gegen ein konfigurierbares
Muster geprüft (Vorgabe: fünf Ziffern).

**Warum:** Ohne E.164 sind Telefonnummern weder vergleichbar (Dublettenprüfung) noch
zuverlässig wählbar (Anrufnachweis in Phase 2) — „0151 1234-5678", „+4915112345678" und
„004915112345678" wären drei verschiedene Leads. Die Handler-Klassen halten
Validierung und Normalisierung an einer Stelle, statt sie über Runtime, API und
Builder zu verteilen.

**Neue Abhängigkeit:** `giggsey/libphonenumber-for-php` (^9.0), vom Ticket vorgegeben
und vom Auftraggeber freigegeben. Eine eigene Regex-Lösung für internationale
Rufnummern wäre der klassische Fall von „funktioniert bis zur ersten Auslandsnummer".

**Neue Config-Keys:** `config/funnel.php` → `question.default_phone_region` (`DE`,
`FUNNEL_QUESTION_DEFAULT_PHONE_REGION`), `question.postal_code_pattern`
(`/^[0-9]{5}$/`, `FUNNEL_QUESTION_POSTAL_CODE_PATTERN`), `question.text_max_length`
(255), `question.textarea_max_length` (2000).

**Migrationen:** keine — `funnel_questions.type` ist bereits `string(32)`, alle
Enum-Werte passen hinein; es ändert sich nur der Cast.

Der Testumfang wurde vom Auftraggeber auf die E.164-Normalisierung begrenzt; die im
Ticket geforderten Tests je Fragetyp stehen als offener Punkt in
[BACKLOG.md](BACKLOG.md).

### FB-030a — OpenAPI-3.1-Spezifikation als Single Source of Truth

**Was:** `docs/openapi.yaml` beschreibt die komplette Management-API v1: den heute
umgesetzten Teil aus FB-006 (`GET /me`, `GET /ping/leads`) und die geplanten Endpunkte
aus FB-030b bis FB-030f — Funnel-CRUD samt verschachtelter Schritte, Fragen, Optionen,
Verzweigungsregeln und Ergebnis-Screens, `PUT /funnels/{funnel}/structure`, Publish,
Duplicate, Archive, Versionshistorie, Leads und Webhook-Verwaltung. Fehler folgen
RFC 9457 (Problem Details). Unter `/docs/api` zeigt eine schlanke Blade-Seite die Datei
über einen CDN-Betrachter an, `/docs/api/openapi.yaml` liefert sie unverändert aus.
`tests/Feature/Funnel/OpenApiContractTest.php` prüft die Datei und hält sie mit der
Anwendung zusammen.

**Warum:** Spezifikation zuerst, Code danach. Ohne verbindlichen Vertrag entscheidet
jedes der fünf Folgetickets für sich, wie eine Antwort aussieht — und die API zerfällt
in fünf Handschriften. Der Contract-Test macht aus dem Dokument eine Zusicherung: jede
als umgesetzt markierte Operation muss als Route existieren, und **jede** registrierte
Route unter `/api/v1` muss beschrieben sein. Ein undokumentierter Endpunkt lässt den
Test fehlschlagen. Geplante Endpunkte sind ausdrücklich erlaubt (`x-status: planned`)
und werden übersprungen, solange FB-030b ff. offen sind.

Zwei Festlegungen, an die sich die Umsetzung zu halten hat:

- **Maskierung als Vertrag.** Ein Lead erscheint als `LeadMasked` oder als `LeadFull` —
  zwei getrennte Schemas, unterschieden über `contact_visibility`, nicht ein Schema mit
  optionalen Kontaktfeldern. Beide verlangen dieselben Kontaktfelder; die maskierte
  Fassung schreibt ihre Kürzung als Muster fest (`a…@example.com`, `+49 30 …`, `76…`).
  Ein unmaskierter Wert an dieser Stelle ist damit vertragswidrig und nicht bloß ein
  Versehen. Der Contract-Test prüft diese Struktur mit.
- **Adressierung.** Ein Funnel wird über seinen `public_token` (ULID) angesprochen, nie
  über die fortlaufende ID; Leads über eine UUID. Die Bausteine eines Funnels (Schritte,
  Fragen, Optionen, Regeln, Ergebnisse) tragen im Schema aus FB-010 keinen eigenen
  öffentlichen Schlüssel und werden über ihre ID adressiert — sie sind nur innerhalb
  eines Funnels erreichbar, den das Token ohnehin besitzt.

Schemas und Enums sind gegen den Ist-Stand modelliert: `FunnelStatus`
(`draft|published|archived`), `ConditionOperator`, `FunnelFieldKey` samt
`einwilligung`, `LeadState`. Felder, deren Tabelle noch fehlt, tragen `x-ticket` mit dem
Ticket, das sie liefert (FB-011, FB-014, FB-031, FB-055) — so muss keines dieser Tickets
die Spezifikation aufreißen. Das schließt `sale_mode`, `max_buyers` und `shared_price`
aus Entscheidung 2 ein.

Keine neue Composer-Abhängigkeit: die Spezifikation wird mit `symfony/yaml` gelesen (über
Laravel ohnehin vorhanden), der Betrachter kommt per CDN. Ein Spec-Validator als
Dev-Abhängigkeit hätte für ein einziges Dokument mehr Wartung als Nutzen gebracht.

**Neue Config-Keys:** `config/funnel.php` → `api.docs_enabled` (`true`,
`FUNNEL_API_DOCS_ENABLED`) und `api.docs_viewer_url` (Scalar über jsDelivr,
`FUNNEL_API_DOCS_VIEWER_URL`). Ist `api.docs_enabled` aus, werden beide Routen nicht
registriert.

**Migrationen:** keine.

### FB-000 — Offene Entscheidungen festschreiben

**Was:** Die fünf offenen Punkte aus Teil 5 der Roadmap sind entschieden, ein sechster
(Anrufnachweis FB-E7 im MVP?) kam dazu und ist ebenfalls entschieden. Teil 5 von
`docs/funnel-builder/roadmap.md` führt sie jetzt mit Status, Entscheidung und
Begründung; `docs/funnel-builder/agent-prompt.md` trägt die Kurzfassung als Abschnitt 6
der „Abweichungen vom Ursprungsdokument".

**Warum:** Solange eine Entscheidung offen ist, muss jedes abhängige Ticket eine eigene
Annahme treffen — und zwei Tickets treffen selten dieselbe. Festgeschrieben und im
Master-Prompt sichtbar, werden sie nicht in jedem Ticket neu aufgeworfen.

Die Entscheidungen im Einzelnen: (1) Guthaben per Stripe ist der Standardweg, Rechnung
nur als manuelle `adjustment`-Buchung im `credit_ledger`. (2) `sale_mode = exclusive`
ist Default (`max_buyers` 1, 15,00 €); `shared` wird vollständig implementiert, mit den
Vorgabewerten `max_buyers` 3 und `shared_price` 7,50 €. (3) Reklamationsfrist 7 Tage,
deckungsgleich mit `call.deadline_days`. (4) Vermittlerregister-Nummer nach § 34d GewO
optional, AV-Vertrag-Checkbox weiterhin Pflicht. (5) Kein JSON-Ticketformat, `tickets.md`
bleibt Markdown. (6) FB-E7 (FB-080…085) wird nicht gebaut; FB-058 ist damit der einzige
Weg von `verkauft` nach `erreicht`/`unerreichbar` und wird vollwertig gebaut.

**Neue Config-Keys:** keine. Die Vorgabewerte für `shared` aus Entscheidung 2 legt
FB-055 an.

### FB-030 — LeadState-Zustandsmaschine

**Was:** Der Zustand eines Leads liegt in genau einer Spalte (`leads.lead_state`) und
wechselt an genau einer Stelle: `App\Services\LeadStateService::transition()`. Erlaubt
ist ausschließlich, was in `App\Constants\LeadTransitions::TABLE` steht (zwölf Übergänge);
alles andere wirft `App\Exceptions\IllegalLeadTransition`. Zustandsänderung und
Protokolleintrag in `lead_state_log` entstehen in einer Transaktion, danach wird
`App\Events\Lead\LeadStateChanged` ausgelöst. Beim Eintritt in einen Endzustand
(`erreicht`, `unerreichbar`, `ungueltig`, `abgelaufen`) werden `settled_price` und
`settled_at` einmalig geschrieben und nie wieder geändert. Protokolleinträge sind
unveränderlich — Model und Query-Builder werfen `LeadStateLogIsImmutableException`,
gleiches Muster wie das Audit-Log aus FB-005.

**Warum:** Der Lead-Zustand entscheidet über Sichtbarkeit von Kontaktdaten, über
Gutschriften und über die Abrechnung. Läge er in mehreren Flags oder ließe er sich an
beliebiger Stelle setzen, gäbe es keinen Zeitpunkt, zu dem eine Aussage über einen Lead
belastbar wäre. Ein einziger Eingang mit unveränderlichem Protokoll macht jeden Wechsel
nachweisbar; ein festgeschriebener Preis kann seine Grundlage nicht nachträglich
verlieren.

**Neue Config-Keys:** keine. Der Abrechnungspreis wird aus dem bestehenden
`config/funnel.php` → `lead.default_price` (FB-004) gelesen.

**Migrationen:** `2026_09_06_130000_create_leads_table` (id, tenant_id, lead_state
Default `neu`, settled_price, settled_at, Zeitstempel) und
`2026_09_06_130100_create_lead_state_log_table` (lead_id, from_state, to_state, reason,
actor_id, meta JSON, created_at) — beide additiv, mit `down()`.

Bewusste Abgrenzungen, damit FB-010 und FB-030 sich nicht überschneiden:

- Die `leads`-Tabelle ist ein Grundgerüst ohne Fremdschlüssel auf Funnel-Tabellen.
  `funnel_id`, `funnel_version_id`, `score`, `result_id`, `price_at_creation`, die
  Kontaktfelder und `lead_answers` kommen additiv in FB-031.
- Der Abrechnungspreis wird über `settlementPriceFor()` aufgelöst: liegt am Lead eine
  `price_at_creation` (FB-031), gilt sie; sonst der Konfigurationswert. FB-031 muss
  dafür nichts am Dienst ändern.
- `Lead` nutzt den in FB-010 entstandenen Trait `BelongsToTenant` statt einer eigenen
  Mandantenlogik. Ohne Mandantenkontext (Konsole, Queue) greift der Scope bewusst nicht;
  die Cross-Tenant-Tests gehören zu dem Ticket, das die erste lesende Sicht auf Leads
  baut (FB-034 bzw. FB-030d).

### FB-010 — Datenmodell Funnel / Steps / Questions / Options

**Was:** Sechs neue Tabellen (`funnels`, `funnel_steps`, `funnel_questions`,
`funnel_options`, `funnel_conditions`, `funnel_results`) mit Models, Factories und einem
Beispiel-Seeder. `funnel_conditions` und `funnel_results` bringen nur die Struktur mit —
StepResolver (FB-012), ResultResolver und die Publish-Validierung der Score-Bereiche
(FB-013) kommen später. Die Vergleichsoperatoren liegen als Enum `ConditionOperator`
(`equals`, `not_equals`, `in`, `gt`, `lt`, `contains`, `answered`, `score_gte`) fest. Ein Funnel wird
öffentlich ausschließlich über `public_token` (ULID, automatisch vergeben, zugleich
Route-Key) adressiert, nie über die ID. Der Feldschlüssel einer Frage wird beim
Speichern normalisiert („E-Mail" → `e_mail`) und ist je Funnel eindeutig — dafür trägt
`funnel_questions` neben `step_id` auch `funnel_id`, sonst wäre ein Unique-Index über
Schrittgrenzen hinweg nicht möglich. Neu sind außerdem das Enum `FunnelStatus`
(`draft|published|archived`), das Enum `FunnelFieldKey` mit den sieben reservierten
Kontakt-Feldschlüsseln und das Trait `BelongsToTenant`, das den Mandantenfilter setzt
und `tenant_id` beim Anlegen automatisch füllt.

**Warum:** Das Funnel-Schema ist die Grundlage für Builder (FB-011 ff.), öffentliche
Runtime (FB-E2) und Lead-Erzeugung. Der Preis eines Leads hängt am Funnel
(`lead_price`, ohne eigenen Wert greift `config('funnel.lead.default_price')`), damit
die kaufmännische Vorgabe nicht im Code steht.

Der Feldschlüssel entsteht in zwei Schritten: `normalize()` vereinheitlicht die
Schreibweise („E-Mail" → `e_mail`), `resolve()` bildet das Ergebnis anschließend über
`config('funnel.field_key_aliases')` auf den reservierten Schlüssel ab (`e_mail` →
`email`). Ohne den zweiten Schritt entstünde ein Lead, dessen Kontaktdaten `LeadContact`
(FB-032) später nicht findet — siehe Abweichung 5 in
[agent-prompt.md](funnel-builder/agent-prompt.md).

**Neue Config-Keys:** `config/funnel.php` → `field_key_aliases` (Zuordnung
gebräuchlicher Schreibweisen auf die reservierten Kontakt-Feldschlüssel, ohne
env-Fallback — eine Tabelle, kein Schwellwert).

**Migrationen:** `2026_09_06_140000_create_funnel_tables` legt die sechs Tabellen an —
additiv, mit `down()` in umgekehrter Reihenfolge. Fremdschlüssel kaskadieren
(Funnel → Schritte → Fragen → Optionen).

Der Beispiel-Funnel wird bewusst nicht vom `DatabaseSeeder` mitgezogen, sondern gezielt
aufgerufen: `php artisan db:seed --class=FunnelExampleSeeder`. Die vollständige
Pfotencheck-Vorlage bleibt FB-019. Zwei Beobachtungen außerhalb des Ticketumfangs
stehen in [BACKLOG.md](BACKLOG.md).

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

Anlegen und Widerrufen sind über `AuditLogger::log()` als Pflichtereignisse aus FB-005
verdrahtet (`AuditAction::API_TOKEN_CREATED` und `API_TOKEN_DELETED`). Protokolliert
werden Bezeichnung, bereinigte Abilities und Token-ID — nie der Klartext des Tokens.
Ein fehlgeschlagener Widerruf (fremdes Token) schreibt keinen Eintrag.

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
