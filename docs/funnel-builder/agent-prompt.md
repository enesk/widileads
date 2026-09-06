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

### 5. Feldschlüssel: Normalisierung **und** Alias-Auflösung

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

### 6. Entschiedene Grundsatzfragen (2026-09-06)

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
