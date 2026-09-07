# Teil 1 — Master-Prompt (jedem Agenten voranstellen)

**Projektkey:** `FB` · **Epic-Präfix:** `FB-E*` · **Ticket-Präfix:** `FB-###`
**Stack:** Laravel 13 · SaasyKit · Livewire 4 · Alpine.js 3 · Tailwind 4 · Filament 5 (nur Admin) · MySQL 8 · Redis/Horizon
**Referenzfunnel:** https://pfotencheck.tierarztportal.com/ (Quiz-Funnel „Pfotencheck" auf tierarztportal.com — Tierhalter beantworten Fragen zu Tierart, Alter, Gesundheit; Ergebnis-Screen mit Empfehlung; Kontaktdaten am Ende → Lead für Tierkrankenversicherung)

Der folgende Block wird jedem Agenten wörtlich vorangestellt. Er entspricht Teil 1 des
Ursprungsdokuments mit den vom Auftraggeber bestätigten Korrekturen, die weiter unten
im Abschnitt [Abweichungen vom Ursprungsdokument](#abweichungen-vom-ursprungsdokument)
einzeln aufgeführt sind.

```
Du arbeitest als Senior-Laravel-Entwickler an der Plattform „Funnel Builder" — einem
Multi-Tenant-System auf Laravel 13 + SaasyKit, mit dem dynamische Lead-Funnels
(Klickstrecken / Quiz-Funnels wie https://pfotencheck.tierarztportal.com/) angelegt,
über eine REST-API verwaltet, auf beliebigen Webseiten eingebettet und die daraus
entstehenden Leads an Käufer (zunächst Versicherungsagenturen) verkauft werden.

## Geschäftsmodell
- Der Plattformbetreiber (Enes) baut Funnels und bettet sie auf seinen Branchenportalen
  ein (z. B. tierarztportal.com → Pfotencheck → Tierkrankenversicherung).
- Endkunden füllen den Funnel anonym aus. Aus jeder vollständigen Anfrage entsteht ein Lead.
- Käufer (Tenants mit Rolle „buyer") kaufen Leads: über Guthaben-Pakete (SaasyKit
  Billing/Stripe) oder auf Rechnung. Der Lead-Preis ist je Funnel konfigurierbar (Vorgabe
  15,00 €). Abgerechnet wird erst, wenn der Lead nachweislich erreicht wurde
  (Anrufnachweis, Phase 2) — bis dahin ist der Preis „festgeschrieben, aber unbelegt".
- Kontaktdaten des Leads sind vor dem Kauf verdeckt; sichtbar sind Qualifizierungsdaten
  (Tierart, Alter, PLZ-Region, Antworten). Nach Kauf: Klartext.

## Architekturleitsätze (nicht verhandelbar)
1. Ein Zustand, eine Spalte: `leads.lead_state` (Enum) ist die EINZIGE Zustandsspalte
   eines Leads. Keine Boolean-Flags wie is_reached/is_fake daneben. Nie.
2. Jeder Zustandswechsel geht durch `LeadStateService::transition()`. Direktes
   `update(['lead_state' => …])` außerhalb dieses Dienstes ist ein Bug; ein Architektur-
   Test (FB-042) schlägt fehl, wenn er auftaucht.
3. Beweis vor Bewertung: Rohdaten (Webhook-Payloads, Submission-Rohantworten) werden
   gespeichert, BEVOR irgendetwas bewertet oder abgeleitet wird.
4. Geld folgt Belegen: Preise werden bei Eintritt eines Endzustands einmalig in
   `leads.settled_price` festgeschrieben und danach nie geändert.
5. Maskierung serverseitig: Kontaktdaten werden im Backend maskiert, bevor sie ein View
   oder eine API-Response erreichen. Maskierung im Blade-Template gilt als nicht erfüllt.
6. Additive Migrationen: migrate:fresh/refresh/reset und db:wipe sind in allen
   Umgebungen über einen DestructiveCommandGuard gesperrt.
7. Öffentliche Funnels laufen über nicht erratbare Tokens (ULID/UUID), nie über IDs.
8. Alle Schwellwerte (Fristen, Preise, Limits) liegen in config/funnel.php, nie im Code.

## SaasyKit-Nutzung
- Verwenden: Tenants, Users, Rollen/Permissions, Einladungen, Plans/Subscriptions,
  Stripe-Integration, Filament-Admin, Mail/Notifications.
- Nicht anfassen / nicht ausbauen: Blog, Roadmap, Announcements, Referral. Diese Module
  bleiben deaktiviert (Feature-Flags in config), werden aber nicht entfernt.
- Tenant-Kontext kommt aus SaasyKit (aktueller Tenant über Session). Neue Tabellen mit
  Mandantenbezug tragen `tenant_id` + Global Scope `BelongsToTenant`.
- Rollen (tenant_user.role): owner, admin, member, buyer. Plattform-Admin ist ein
  SaasyKit-Admin-User (Filament-Panel /admin).

## Technische Konventionen
- PHP 8.4, strict_types, finale Klassen wo sinnvoll, Enums für alle Zustandswerte.
- Fachlogik in app/Services und app/Actions, dünne Controller/Livewire-Komponenten.
- Form Requests für Validierung; API-Resources für jede API-Antwort; API unter /api/v1,
  Auth via Laravel Sanctum (personal access tokens je Tenant).
- Oberfläche und alle Meldungen deutsch, über lang/de/*.php. Keine hartkodierten Strings.
- Tests: PHPUnit. Kein Pest — das Projekt bleibt bei PHPUnit. Jede Muss-Anforderung eines
  Tickets hat mindestens einen Test. Fachlogik (Zustände, Preise, Bewertung) als
  Unit-Tests ohne Datenbank.
- Larastan: Zielniveau Level 6. Der Ist-Wert in phpstan.neon ist Level 3; die Anhebung
  auf Level 6 ist Aufgabe von FB-001. Pint (PSR-12). Keine N+1 (Tests mit
  `$this->assertQueryCountLessThan()` oder preventLazyLoading im Testing-Env).
- Migrationen: eine pro Ticket-Thema, additive, mit Down-Methode.
- Commits: `FB-###: <Kurzbeschreibung>`.

## Definition of Done je Ticket
- Alle Akzeptanzkriterien erfüllt und durch Tests belegt.
- `php artisan test`, `vendor/bin/phpstan`, `vendor/bin/pint --test` grün.
- Keine neuen Zustandsspalten, keine direkten Zustands-Updates, keine Klartext-
  Kontaktdaten in Views/Responses vor Kauf.
- Kurze Notiz in docs/CHANGELOG.md: was, warum, welche Config-Keys neu sind.
- Vor dem Melden auf aktuelles main rebasen und `composer check` erneut laufen lassen.

## Arbeitsweise
- Lies das Ticket vollständig. Prüfe die „Abhängigkeiten" — existiert der referenzierte
  Code, baue darauf auf; erfinde keine Parallelstrukturen.
- Lies vor dem Anlegen einer Tabelle das Datenmodell in docs/funnel-builder/datenmodell.md.
- Bei Unklarheit: Annahme treffen, im Ticket dokumentieren, weiterarbeiten. Nicht blockieren.
- Erweitere den Umfang nicht. Wünsche, die dir auffallen, kommen in docs/BACKLOG.md.
```

---

## Abweichungen vom Ursprungsdokument

Die folgenden Punkte weichen bewusst vom Ursprungsdokument ab. Sie sind vom Auftraggeber
bestätigt und haben Vorrang vor dem dortigen Wortlaut.

### 1. Testframework: PHPUnit, nicht Pest

**Ursprungsdokument:** „Tests: PHPUnit/Pest." (Teil 1), „Pest einrichten" (FB-001),
„Architektur-Tests (Pest Arch)" (FB-042).

**Verbindlich:** Das Projekt bleibt bei **PHPUnit** (`phpunit/phpunit: ^11.0`), wie in
`CLAUDE.md` / `AGENTS.md` festgelegt. Pest wird nicht eingeführt.

- FB-001 richtet **kein** Pest ein; `composer check` bleibt `pint --test` + `phpstan` +
  `php artisan test` auf PHPUnit-Basis.
- **FB-042 „Pest Arch Tests" werden als normale PHPUnit-Tests umgesetzt** — die
  Architekturregeln (kein `lead_state`-Update außerhalb `LeadStateService`, keine
  Config-Werte im Code, keine Klartext-Kontaktausgabe außer über `LeadContact`) werden
  als reguläre PHPUnit-Testfälle geprüft, etwa über Reflection- und Grep-basierte
  Assertions über das `app/`-Verzeichnis.
- Neue Tests werden mit `php artisan make:test --phpunit {name}` erzeugt.
- Sollte irgendwo Pest-Syntax auftauchen, wird sie nach PHPUnit überführt.

### 2. Stack-Versionen: Ist-Stand des Repositories

**Ursprungsdokument:** „Laravel 12 · SaasyKit · Livewire 3 · Alpine.js · Tailwind ·
Filament (nur Admin)", „PHP 8.3+".

**Verbindlich:** die tatsächlich installierten Versionen laut `composer.json` und
`package.json`:

| Baustein | Ursprungsdokument | Ist-Stand im Repository |
|---|---|---|
| PHP | 8.3+ | **^8.4** |
| laravel/framework | 12 | **^13.0** |
| filament/filament | 4 | **^5.0** |
| livewire/livewire | 3 | **^4.0** |
| alpinejs | (ohne Version) | ^3.13 |
| tailwindcss | (ohne Version) | ^4.1 (+ daisyUI ^5.0) |
| laravel/horizon | — | ^5.21 |
| laravel/sanctum | — | ^4.0 |
| phpunit/phpunit | (Pest genannt) | **^11.0** |
| larastan/larastan | — | ^3.0 |
| laravel/pint | — | ^1.0 |

Wo ein Ticket auf Framework-spezifische Bestandteile verweist (Filament-Actions,
Livewire-Komponenten, Namespaces), gelten die Konventionen der installierten
Hauptversionen — insbesondere die Filament-5- und Livewire-4-Namespaces und -APIs aus
den Projektrichtlinien in `CLAUDE.md`, nicht die der im Dokument genannten Vorgänger.

Die Formulierung „Frisches SaasyKit-Projekt auf Laravel 12" in FB-001 ist damit
gegenstandslos: Das Projekt existiert bereits auf dem oben genannten Stand; FB-001
konfiguriert es, es wird nicht neu aufgesetzt.

### 3. Larastan-Level: Ist 3, Ziel 6 über FB-001

**Ursprungsdokument:** „Larastan Level 6" (Teil 1) und „Larastan (Level 6) … einrichten"
(FB-001).

**Ist-Stand:** `phpstan.neon` steht auf **Level 3**:

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app/
    # Level 9 is the highest level
    level: 3
```

**Verbindlich:** Level 3 ist der aktuelle Stand. Die **Anhebung auf Level 6 ist Aufgabe
von FB-001** — inklusive der dafür nötigen Bereinigung des Bestandscodes bzw. gezielter,
begründeter `ignoreErrors`-Einträge. Bis FB-001 abgeschlossen ist, gilt für die
Definition of Done „`vendor/bin/phpstan` grün" auf dem jeweils in `phpstan.neon`
eingetragenen Level. Ab Abschluss von FB-001 gilt Level 6 für alle Folgetickets.

### 4. Verweis auf das Datenmodell

Im Ursprungsdokument verweist die Arbeitsweise auf „Teil 2 dieses Dokuments". Da das
Dokument hier auf mehrere Dateien aufgeteilt ist, verweist der Prompt stattdessen auf
`docs/funnel-builder/datenmodell.md`. Inhaltlich unverändert.

### 5. „Kein Filament im Tenant-Dashboard" — Zuschnitt

**Ursprungsdokument:** „Keine Filament-Nutzung im Tenant-Dashboard." (FB-015)

**Verbindlich:** Gemeint sind **keine Filament-Formular- und Tabellen-Builder für
Tenant-Oberflächen** — nicht „am Panel vorbei routen". Das Tenant-Dashboard *ist* in
SaasyKit ein Filament-Panel; Routing, Navigation und Tenant-Switcher hängen daran.
Eine eigene Route außerhalb des Panels wäre die Parallelstruktur, die der Master-Prompt
untersagt.

Der gewünschte Zuschnitt, erstmals in FB-006 umgesetzt:

- **Hülle:** eine dünne `Filament\Pages\Page` in `app/Filament/Dashboard/Pages/`, die
  nur Routing, Navigationseintrag, Titel und `canAccess()` beisteuert. Ihr Blade-View
  enthält ausschließlich `<x-filament-panels::page>` und das eingebettete Livewire.
- **Inhalt:** eine gewöhnliche Livewire-Komponente in `app/Livewire/Dashboard/` mit
  Blade, Tailwind und daisyUI. Keine `InteractsWithTable`, `InteractsWithForms` oder
  `Filament\Actions` — Formulare, Tabellen und Bestätigungsdialoge werden mit
  Livewire-Bordmitteln gebaut (`wire:model`, `wire:submit`, `wire:confirm`,
  `$this->validate()`).

Referenz: `App\Filament\Dashboard\Pages\ApiTokens` (Hülle) und
`App\Livewire\Dashboard\ApiTokens` (Inhalt).

Gilt genauso für FB-015 (Builder), FB-034 (Lead-Liste) und FB-053 (Marktplatz).

Im Admin-Panel (`/admin`) bleibt Filament uneingeschränkt das Mittel der Wahl.

### 6. Feldschlüssel: Normalisierung **und** Alias-Auflösung

**Ursprungsdokument:** FB-010 verlangt, dass der Feldschlüssel beim Speichern
normalisiert wird, mit dem Beispiel „E-Mail" → „e_mail", und nennt separat die
reservierten Feldschlüssel `vorname`, `nachname`, `name`, `email`, `telefon`, `plz`,
`einwilligung`.

**Verbindlich:** Beide Vorgaben zusammen ergeben eine Falle mit Datenfolge. Ein
Funnel-Ersteller beschriftet sein Kontaktfeld naheliegend mit „E-Mail", der Schlüssel
wird zu `e_mail` — und trifft damit den reservierten Schlüssel `email` nicht. Alles
sieht richtig aus, aber `LeadContact` (FB-032) findet später keine E-Mail-Adresse; der
Lead entsteht ohne auflösbaren Kontakt und ist wertlos. Auffallen würde das erst in
Produktion, wenn die unbrauchbaren Leads bereits gespeichert sind.

Deshalb entsteht ein Feldschlüssel in **zwei** Schritten:

1. `FunnelFieldKey::normalize()` vereinheitlicht die Schreibweise — unverändert die
   Regel aus dem Ticket, „E-Mail" ergibt „e_mail".
2. `FunnelFieldKey::resolve()` bildet das Ergebnis anschließend über
   `config('funnel.field_key_aliases')` auf den reservierten Schlüssel ab, „e_mail"
   wird zu „email". Ohne hinterlegten Alias bleibt der normalisierte Schlüssel stehen.

Gespeichert wird das Ergebnis aus Schritt 2. Die Alias-Tabelle steht in
`config/funnel.php` und ist damit ohne Codeänderung erweiterbar; Aliase, die auf einen
nicht existierenden reservierten Schlüssel zeigen, werden ignoriert.

Umgesetzt in FB-010.

### 7. Entschiedene Grundsatzfragen (2026-09-06)

Die sechs Punkte aus [roadmap.md](roadmap.md#teil-5--entscheidungen-enes) sind
entschieden. Sie gelten für alle Tickets und werden dort **nicht neu aufgeworfen** —
wer meint, eine davon sei falsch, meldet das, statt sie im Ticket umzudeuten. Die
ausführliche Begründung steht jeweils in Teil 5 der Roadmap.

| Nr. | Entschieden | Betrifft |
|:-:|---|---|
| 1 | **Guthaben per Stripe ist der Standardweg.** Rechnung nur als manuelle `adjustment`-Buchung im `credit_ledger` durch den Plattform-Admin — kein Rechnungslauf, kein Mahnprozess. | FB-052, FB-059 |
| 2 | **`sale_mode = exclusive` ist Default** (`max_buyers` 1, 15,00 €). `shared` wird vollständig implementiert, aber nicht als Default; Vorgabewerte `max_buyers` 3 und `shared_price` 7,50 € stehen in `config/funnel.php`. | FB-055 |
| 3 | **Reklamationsfrist 7 Tage**, deckungsgleich mit `call.deadline_days`. Kein zweites Zeitmaß. | FB-058 |
| 4 | **Vermittlerregister-Nummer (§ 34d GewO) ist optional**, nicht Pflicht. Die AV-Vertrag-Checkbox bleibt Pflicht mit Zeitstempel. | FB-050 |
| 5 | **Kein JSON-Ticketformat.** `docs/funnel-builder/tickets.md` bleibt als Markdown die einzige Arbeitsgrundlage. | tickets.md |
| 6 | **FB-E7 (FB-080…085, Anrufnachweis) wird nicht gebaut.** Folge: FB-058 ist der einzige Weg von `verkauft` nach `erreicht`/`unerreichbar` und wird vollwertig gebaut — Operator-Prüfqueue, Gutschrift über `credit_ledger` `refund`, Reklamationsquote je Käufer. Kein Provisorium. | FB-080…085, FB-058 |

### 8. Testumfang (Entscheidung Enes, 2026-09-06)

**Ursprungsdokument:** „Jede Muss-Anforderung eines Tickets hat mindestens einen Test."
(Teil 1)

**Verbindlich:** Der Testumfang wird drastisch reduziert.

**Keine Tests mehr für:**

- Migrationen, Schema, Spalten, Casts, Enums, Factories, Seeder
- Konfigurationswerte
- Blade-/UI-Rendering, Navigation, Filament-Ressourcen
- Getter, Setter, Relationen, Scopes, Route-Keys
- CRUD ohne Fachlogik
- Sprachdateien

Bei reinen Schema- und UI-Tickets ist **null Tests der Normalfall**, nicht die Ausnahme.

**Tests nur dort, wo ein Fehler teuer ist und still passiert:**

- Geld: Kauf, `credit_ledger`, `settled_price`, Guthabenprüfung, Gutschriften
- `lead_state`-Übergänge und Unveränderlichkeit des Protokolls
- Maskierung von Kontaktdaten
- Mandantentrennung und Cross-Tenant-Zugriff
- Nebenläufigkeit (zwei Käufer, ein Lead)
- Normalisierung mit Datenfolge (E.164, `field_key`-Aliase)
- Spam- und Dublettenregeln
- Signaturprüfung bei Webhooks

**Richtwert:** höchstens drei bis fünf Tests je Ticket, und nur aus dieser Liste.
Bestehende Tests werden **nicht** entfernt. `composer check` bleibt Pflicht.

**Verhältnis zum Ursprungsdokument:** Die Vorgabe „jede Muss-Anforderung hat mindestens
einen Test" ist damit überstimmt und gilt nur noch für die oben aufgezählten Bereiche.

#### Zusatz: keine Tests über die Testinfrastruktur

Änderungen an `tests/TestCase.php`, `phpunit.xml`, Test-Traits und Hilfsklassen bekommen
keinen eigenen Test, der prüft, dass die Infrastruktur funktioniert. Der grüne Lauf der
Suite ist der Beleg.

Begründung: Ein Test über die Testinfrastruktur läuft mit derselben Infrastruktur, die
er prüfen soll. Er kostet Laufzeit und Pflege, ohne unabhängige Aussagekraft zu haben,
und er zementiert Implementierungsdetails, die sich mit jedem Umbau der Suite ändern.

Erstmals angewandt in FB-040: Die dort gebaute Isolation (statische Caches leeren,
eigene Test-Datenbank je Arbeitskopie) ist durch `composer check:determinism` belegt —
drei Läufe mit unterschiedlichen Seeds, gleiches Ergebnis — statt durch Wächter-Tests.

Unberührt bleibt: Wo Infrastrukturarbeit einen **fachlichen** Fehler behebt oder
Fachcode ändert, gehört der Test dorthin, wo die Fachlogik liegt.

#### Präzisierung: Erreichbarkeit ist kein Aussehen (2026-09-07)

Der Ausschluss von UI-Rendering meint das **Aussehen** — Beschriftungen, Reihenfolge,
Struktur, Markup. Das ist billig zu reparieren und teuer zu testen.

Eine Seite, die mit 500 **gar nicht mehr lädt**, ist kein Aussehen. Das ist
Totalausfall, er passiert still, und `composer check` ist dabei grün.

**Verbindlich:** Je Panel ein Rauchtest, der belegt, dass eine Seite überhaupt lädt —
anmelden, Seite aufrufen, 200 erwarten. Kein Inhalt, keine Struktur, keine
Bezeichnungen. Alles darüber hinaus bleibt testfrei.

Umgesetzt in `tests/Feature/Funnel/PanelSmokeTest.php`: drei Tests, Admin-Panel sowie
Dashboard eines Betreiber- und eines Käufer-Mandanten. Anlass waren zwei Fehler, die
jede Seite eines Panels gleichzeitig getroffen haben und trotzdem grün ausgeliefert
worden wären — beide stehen in Abschnitt 12.

### 9. Sammeldateien vermeiden: eine Datei je Zuständigkeit (2026-09-06)

**Anlass:** Vier PRs in Folge mussten allein wegen derselben Sammeldateien rebasen. Die
Ursache ist mechanisch, nicht inhaltlich: Wenn jedes Ticket seinen Abschnitt **oben** in
`docs/CHANGELOG.md` einfügt, kollidieren zwei parallel laufende Tickets dort
zwangsläufig — dieselbe Zeile, zwei Änderungen. Bei mehreren gleichzeitigen Sessions ist
das ein Dauerzustand.

**Verbindlich:**

- **`docs/CHANGELOG.md` wird nicht mehr geändert.** Jedes Ticket legt stattdessen
  `docs/changelog.d/FB-###.md` an — eine Datei je Ticket, damit sind Konflikte
  ausgeschlossen. Format und Konventionen stehen in
  [`docs/changelog.d/README.md`](../changelog.d/README.md). Die vorhandenen Einträge in
  `docs/CHANGELOG.md` bleiben als Archiv stehen.
- **Übersetzungen:** neue Blöcke kommen **nicht** mehr in `lang/*/funnel.php`, sondern in
  eine eigene Themendatei je Zuständigkeit — `lang/de/<thema>.php` und
  `lang/en/<thema>.php`, angesprochen als `__('<thema>.schluessel')`. Zum Beispiel
  `builder.php` für den Funnel-Builder, `marketplace.php` für den Marktplatz,
  `runtime.php` für die öffentliche Strecke. Bestehende Schlüssel in `funnel.php` bleiben,
  wo sie sind; die Regel gilt nur für neue Blöcke.

  Anhängen ans Dateiende — die erste Fassung dieser Regel — hat das Problem nur
  verschoben: zwei Sessions hängen an dieselbe Stelle an und kollidieren genauso
  (passiert zwischen FB-014 und FB-038). Eine Datei je Zuständigkeit schließt es aus,
  gleiches Prinzip wie bei `docs/changelog.d/FB-###.md`.
- **`config/funnel.php`:** neue Keys als eigener Block ans **Ende der jeweiligen
  Sektion**. Bestehende Zeilen werden nie umformatiert, auch nicht „nur schnell"
  ausgerichtet — jede Umformatierung erzeugt einen Konflikt für jeden offenen PR.
- **`docs/BACKLOG.md`:** neue Einträge ans Ende der Liste „Einträge". Ein erledigter
  Eintrag wird als erledigt markiert, nicht gelöscht — sonst verschwindet er aus der
  Historie des Tickets, das ihn aufgeschrieben hat.

**Beim Auflösen eines Konflikts in einer dieser Dateien gilt:** rein additiv. Beide
Seiten bleiben vollständig erhalten, kein fremder Eintrag wird geändert, gekürzt oder
umformatiert. Im Zweifel lieber eine Dopplung melden als etwas wegwerfen.

### 10. Migrationszeitstempel: Minutenblock je Session (2026-09-07)

**Anlass:** `php artisan make:migration` nimmt die aktuelle Uhrzeit und weiß nichts von
den anderen Sessions. Bei vier gleichzeitigen Sessions kollidieren Zeitstempel deshalb
regelmäßig — beim Schreiben dieser Regel lagen bereits **drei** Paare vor:

| Zeitstempel | Kollidierende Migrationen |
| --- | --- |
| `2026_09_06_170000` | `add_precision_to_funnel_builder_timestamps` (FB-015) · `create_buyer_registrations_table` |
| `2026_09_06_190000` | `add_origin_to_public_sessions_table` · `create_buyer_profiles_table` |
| `2026_09_07_090000` | `add_evaluate_at_step_position_to_funnel_conditions_table` (FB-012a) · `create_credit_ledger_table` |

Laravel sortiert dann nach Dateinamen, die Reihenfolge ist also deterministisch — aber
sie ist nicht mehr am Zeitstempel ablesbar, und sie ergibt sich aus dem Anfangsbuchstaben
statt aus der Absicht. Bei einer Migration, die auf einer anderen aufbaut, wäre sie
schlicht falsch: `add_…` läuft vor `create_…`, auch wenn die Spalte erst nach der Tabelle
Sinn ergibt.

**Verbindlich:** Die Minute im Zeitstempel gehört der Session:

| Session | Minutenblock |
| --- | --- |
| widileads-2 | `00`–`14` |
| widileads-3 | `15`–`29` |
| widileads-4 | `30`–`44` |
| widileads-5 | `45`–`59` |

Also `2026_09_07_091500_…` für widileads-3, `2026_09_07_093000_…` für widileads-4 und so
weiter. `make:migration` erzeugt den Namen mit der Uhrzeit von jetzt; die Datei danach
umbenennen, bevor sie das erste Mal läuft. Ist sie in der eigenen Entwicklungsdatenbank
schon gelaufen, vorher die Zeile aus `migrations` löschen und die angelegten Tabellen
verwerfen — sonst läuft sie unter dem neuen Namen ein zweites Mal.

Die Stunde bleibt frei wählbar: Reihenfolge innerhalb eines Tages wird über die Stunde
ausgedrückt, die Kollisionsfreiheit über die Minute.

**Bestehende Migrationen werden nicht umbenannt.** Sie sind in allen Entwicklungs- und
Testdatenbanken bereits gelaufen; ein nachträglicher Namenswechsel ließe sie überall ein
zweites Mal laufen. Die Regel gilt für neue Migrationen.

### 11. Vor dem Melden gegen aktuelles main pruefen (2026-09-07)

**Anlass:** Zwei Fehlerbilder, die wir an einem Tag beide hatten. Beim ersten macht eine
Aenderung ihre Nachbarn kaputt (DDL im Testkoerper committet die Transaktion, siehe die
Warnung aus FB-023). Beim zweiten ist eine Aenderung gegen einen Zwischenstand
geschrieben, der beim Mergen schon ueberholt ist — FB-023 sicherte zu, dass es die
FB-031-Spalten *nicht* gibt, und war grün, bis FB-031 landete.

Beide Male ist der PR in seinem eigenen Lauf gruen, und beim Merge faellt nichts auf.

**Verbindlich:** Vor dem Melden eines PR auf aktuelles `main` rebasen und `composer check`
erneut laufen lassen. Der Determinismus-Lauf (`composer check:determinism`) faengt das
erste Fehlerbild zuverlaessig, weil er die Reihenfolge wuerfelt — gegen das zweite hilft
nur, gegen den aktuellen Stand zu pruefen.

`.github/workflows/ci.yml` faehrt `composer check` bei jedem PR. Die Regel gilt trotzdem:
Die CI greift erst nach dem Pushen, und ein Rebase davor spart die Runde aus rotem Lauf,
Nachbessern und erneutem Pushen.

### 12. Zwei Fallstricke, die ein ganzes Panel lahmlegen (2026-09-07)

Beide sind in FB-028 aufgetreten, beide legen **jede** Seite eines Panels gleichzeitig
lahm, und beide sind an der Fundstelle nicht zu erkennen.

#### 12.1 Übersetzungsschlüssel ohne Punkt

`__('Leads')` ist kein harmloser Text. Laravel sucht einen Schlüssel ohne Punkt zuerst
in `lang/<locale>.json`; findet es ihn dort nicht, deutet es ihn als **Dateinamen** und
lädt `lang/de/Leads.php`. Auf macOS — dem Rechner der meisten Entwickler hier —
unterscheidet das Dateisystem Groß- und Kleinschreibung nicht, also trifft das unsere
`lang/de/leads.php`, und `__()` gibt das komplette **Array** zurück.

`getNavigationGroup(): ?string` wirft damit einen `TypeError`, und zwar auf jeder Seite
des Panels. Auf Linux gäbe es die Datei `Leads.php` nicht, dort käme der String zurück.

Der Fehler tritt also **lokal auf und in der CI nicht** — oder umgekehrt, je nachdem,
wo die Datei liegt. Das ist der unangenehmste Fehlertyp, den wir haben: Er widerspricht
dem, was der andere Rechner zeigt, und lässt beide Seiten an ihrem eigenen Aufbau
zweifeln.

**Regel:** Gruppen- und Menübezeichnungen immer über namensraumbehaftete Schlüssel —
`__('builder.groups.leads')`, nie `__('Leads')`. Wo ein Schlüssel ohne Punkt
unvermeidbar ist (SaasyKit übersetzt mit dem englischen Text als Schlüssel), gehört er
in `lang/<locale>.json`; die wird zuerst gelesen, dann findet die Dateisuche nicht mehr
statt. `tests/Unit/Funnel/TranslationKeyCollisionTest.php` prüft das statisch.

#### 12.2 Icons an Navigationsgruppe und Eintrag zugleich

Filament lässt Icons **entweder** an der Navigationsgruppe **oder** an ihren Einträgen
zu, nicht an beidem — sonst bricht es beim Rendern der Seitenleiste mit einer Exception
ab, also wieder auf jeder Seite des Panels:

> Navigation group [X] has an icon but one or more of its items also have icons.

Die mitgelieferten SaasyKit-Gruppen umgehen das nur, weil sie `collapsed()` sind — in
dem Fall verwirft Filament die Icons der Einträge stillschweigend. Eine neue Gruppe ohne
`collapsed()` erbt diesen Schutz nicht.

**Regel:** Neue Navigationsgruppen bekommen **kein** Icon. Die Einträge sind die
aussagekräftigere Stelle, und so bricht nichts, sobald eine künftige Resource ein Icon
mitbringt.
