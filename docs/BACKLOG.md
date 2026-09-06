# Backlog

Hier landen Wünsche, Ideen und Beobachtungen, die den Umfang des jeweils bearbeiteten
Tickets sprengen würden.

Regel aus dem Master-Prompt: **Den Ticketumfang nicht erweitern.** Fällt während der
Arbeit etwas auf, das über das Ticket hinausgeht — eine fehlende Funktion, eine
Verbesserung, eine technische Schuld — wird es hier notiert und nicht nebenbei
umgesetzt. Enes entscheidet, ob und wann daraus ein Ticket wird.

## Format

Ein Eintrag je Zeile bzw. Absatz:

```markdown
- **<Kurztitel>** — was fehlt/stört und warum es wichtig sein könnte.
  Aufgefallen bei: FB-###. Datum: JJJJ-MM-TT.
```

## Einträge

- **Marktplatz filtert in PHP und ist deshalb gedeckelt** — der `LeadMatcher` ist eine reine
  Funktion (Zusage aus FB-051), also lassen sich Regionen- und Antwortfilter nicht in SQL
  ausdrücken. `MarketplaceListing` schränkt in der Datenbank vor, was sie sicher kann
  (Zustand, Betreiber, Funnelauswahl, Mindestpunktzahl), und prüft danach höchstens
  `marketplace.listing.candidate_limit` Leads (Vorgabe 500) mit dem Matcher. Ein Käufer mit
  sehr engen Kriterien und sehr vielen verfügbaren Leads sieht dadurch möglicherweise nicht
  alle Treffer. Bei den erwarteten Größenordnungen unkritisch; wächst der Bestand, sind die
  Optionen: Antwortfilter als generierte Spalten oder JSON-Index abbilden, oder passende
  Leads je Profil vorberechnen (dann aber die Deckungsgleichheit mit dem Matcher absichern,
  sonst laufen Anzeige und Autokauf auseinander).
  Aufgefallen bei: FB-053. Datum: 2026-09-07.
- **`Order`-Beziehungen sind nicht typisiert** — `Order::items()` und `Order::tenant()` tragen
  keine Generics, deshalb liefert PHPStan dort `Model` statt `OrderItem`/`Tenant`. Der Versuch,
  das nachzuziehen, legt in `StripeProvider` und Umgebung rund 50 bislang von der Baseline
  verdeckte Typfehler frei — das ist eine eigene Aufräumaufgabe, kein Nebenbei. Bis dahin
  fragen neue Stellen Positionen und Produkte direkt ab (siehe `BookCreditsOnOrder`).
  Aufgefallen bei: FB-052. Datum: 2026-09-07.
- **Funnelnamen sind über Betreibergrenzen hinweg sichtbar** — `MarketplaceCatalog::publishedFunnels()`
  schaltet den Mandanten-Scope bewusst ab und gibt Kennung und Name aller veröffentlichten
  Funnels von Betreiber-Mandanten an jeden Käufer frei. Ohne das kann ein Käufer seine
  Kaufkriterien nicht auf einzelne Funnels einschränken. Heute unkritisch, weil es genau
  **einen** Betreiber gibt — das ist eine Annahme mit Verfallsdatum. Vor dem zweiten
  Betreiber-Mandanten neu bewerten: entweder auf Funnels einschränken, aus denen der
  Käufer bereits gekauft hat, oder den Namen durch eine neutrale Bezeichnung ersetzen.
  Gehört in die Sicherheits-Checkliste FB-043.
  Aufgefallen bei: FB-051. Datum: 2026-09-07.
- **Verzweigungsregeln greifen nur im Schritt ihrer Ausgangsfrage** — `StepResolver`
  wertet über `FunnelSnapshot::conditionsForStep()` nur die Regeln aus, deren
  `source_field_key` zu einer Frage des gerade verlassenen Schritts gehört. Eine Regel
  an einer früheren Antwort wird nie betrachtet, obwohl alle bisherigen Antworten
  vorliegen. Beim Pfotencheck heißt das: „Tierart = Sonstige überspringt Rasse/Größe"
  (Tierart in Schritt 1, Rasse in Schritt 3) ist nicht abbildbar, ohne auch das Alter in
  Schritt 2 zu überspringen. Ursache ist eine Lücke im Datenmodell aus FB-010: Es sagt,
  **an welcher Frage** eine Regel hängt, aber nicht, **wann** sie ausgewertet wird.
  Möglicher Fix: nullable Spalte `evaluate_at_step_position` an `funnel_conditions`
  (Vorgabe: Schritt der Ausgangsfrage); `conditionsForStep()` filtert dann darauf.
  Auswertung, Operatoren und Zyklenschutz blieben unverändert. Vor FB-016
  (Conditions-Editor) zu entscheiden. Aufgefallen bei: FB-012, gemeldet über FB-019.
  Datum: 2026-09-06.

- **Leads in `neu` oder `reserviert` erreichen die Aufbewahrungsfrist nie** — FB-037
  lässt nur `verfuegbar` ablaufen und anonymisiert nur Endzustände. Bleibt ein Lead
  hängen (Prüfjob aus FB-033 fehlgeschlagen, Reservierung nicht aufgeräumt), wird er
  nie anonymisiert und hält personenbezogene Daten unbegrenzt. Beide Übergänge wären
  laut `LeadTransitions` erlaubt; das Ticket nennt sie nicht, deshalb unverändert
  umgesetzt. Aufgefallen bei: FB-037. Datum: 2026-09-06.

- **Tests je Fragetyp fehlen** — FB-011 fordert als Akzeptanz „Unit-Test je Typ für
  gültige/ungültige Eingaben". Abgesichert ist bisher nur die E.164-Normalisierung
  (Testumfang vom Auftraggeber bewusst darauf begrenzt). Ungetestet sind damit die
  Regeln und Normalisierungen der übrigen zwölf Typen — etwa dass eine Pflicht-
  Einwilligung `accepted` verlangt, `multi_choice` immer eine Liste liefert oder
  `date` auf ISO-8601 vereinheitlicht. Aufgefallen bei: FB-011. Datum: 2026-09-06.

- **~~Test-Datenbank ist zwischen Sessions geteilt~~ (erledigt in FB-040)** — `.env.testing`
  zeigte fest auf `saasykit_tenancy_test`. Liefen zwei Sessions gleichzeitig
  `php artisan test`, brachen die Läufe gegenseitig ab („Table 'permissions' already
  exists", „Unknown column 'uuid'"). `Tests\TestCase` leitet den Datenbanknamen jetzt
  aus dem Pfad der Arbeitskopie ab und legt die Datenbank beim ersten Lauf selbst an;
  ein ausdrücklich gesetztes `DB_DATABASE` behält Vorrang.
  Aufgefallen bei: FB-010. Erledigt in: FB-040. Datum: 2026-09-06.

- **`Model::preventLazyLoading()` im Testing-Env erzeugt 34 Fehler** — in FB-040
  probeweise aktiviert und wieder zurückgenommen, weil die Treffer ausschließlich im
  Bestandscode liegen und ein Umbau laut Ticket FB-041 überlassen ist. Verteilung:
  27× `[roles]` auf `App\Models\User` (Spatie `HasRoles` lädt die Rollen bei jeder
  Berechtigungsprüfung nach, u. a. im Filament-Admin-Panel), 2× `[plan]` auf
  `App\Models\Subscription` (`SubscriptionService.php:467`), Rest verteilt.
  Betroffen sind ~20 Testklassen quer durch Admin-Resources und Services. Der Hebel
  liegt beim `roles`-Fall: einmal gelöst, bleibt fast nichts übrig.
  Aufgefallen bei: FB-040. Datum: 2026-09-06.

- **Horizon: `horizon:snapshot` ist nicht eingeplant** — ohne
  `Schedule::command('horizon:snapshot')->everyFiveMinutes()` in `routes/console.php`
  bleibt die Metrics-Seite von Horizon dauerhaft leer. Bewusst nicht in FB-001
  geändert, weil es das Scheduling-Verhalten ändert.
  Aufgefallen bei: FB-001. Datum: 2026-09-06.
- **Horizon: keine `staging`-Umgebung** — `config/horizon.php` kennt nur `production`
  und `local`. In einer `staging`-Umgebung startet Horizon keine Supervisor und
  verarbeitet keine Jobs. Vor dem ersten Staging-Deployment ergänzen.
  Aufgefallen bei: FB-001. Datum: 2026-09-06.
- **`QUEUE_CONNECTION=sync` in `.env.example`** — für den Betrieb mit Horizon muss
  `redis` gesetzt werden. Im README dokumentiert, der Auslieferungswert wurde nicht
  geändert, um bestehende Setups nicht umzustellen.
  Aufgefallen bei: FB-001. Datum: 2026-09-06.
- **Zwei echte Bugs im Bestandscode (PHPStan Level 0)** — 
  `app/Services/PaymentProviders/LemonSqueezy/LemonSqueezyProvider.php:181` nutzt die
  undefinierte Variable `$product`; `app/Services/TenantService.php:211` greift auf die
  Relation `tenant` zu, die es auf `App\Models\Invitation` nicht gibt. Beide sind in
  der PHPStan-Baseline eingefroren und sollten beim nächsten Anfassen der Dateien
  behoben werden. Aufgefallen bei: FB-001. Datum: 2026-09-06.
- **Übersetzungsverzeichnis war doppelt** — `resources/lang/` und `lang/` existierten
  parallel; Laravel bevorzugt `resources/lang/`, wodurch die Dateien unter `lang/`
  (inkl. der vollständigen `validation.php`) wirkungslos waren. In FB-001 auf `lang/`
  konsolidiert, weil deutsche Strings unter `lang/de/` sonst nie gegriffen hätten.
  Übersetzungen gehören ab sofort ausschließlich nach `lang/<locale>/`.
  Aufgefallen bei: FB-001. Datum: 2026-09-06.
- **Admin-Routen abgeschalteter Module liefern 403 statt 404** — Filament registriert
  die Resource-Routen unabhängig von `canAccess()`. Die Navigationseinträge sind weg
  und der Zugriff ist gesperrt, aber die URLs existieren. Für 404 müssten die
  Resources bedingt aus dem Panel-Discovery genommen werden.
  Aufgefallen bei: FB-001. Datum: 2026-09-06.

## Larastan: Zielniveau Level 6 (FB-001)

**Ist-Zustand:** `phpstan.neon` steht auf **Level 6**. Der Bestandscode aus dem
SaaSykit-Starterkit erfüllt dieses Niveau nicht, deshalb sind die bestehenden
**1216 Fehler** in `phpstan-baseline.neon` eingefroren. Neuer und geänderter Code wird
ab sofort vollständig gegen Level 6 geprüft.

Ein schrittweises Anheben ohne Baseline war nicht möglich: der Bestandscode scheitert
bereits an **Level 0**. Ein Umbau des Bestandscodes ist laut FB-001 ausdrücklich nicht
Teil des Tickets, deshalb die Baseline. Fehler pro Level (Pfade `app/`, `routes/`,
`config/funnel.php`):

| Level | Fehler im Bestandscode |
| ----- | ---------------------- |
| 0     | 2                      |
| 1     | 48                     |
| 2     | 367                    |
| 3     | 387                    |
| 4     | 413                    |
| 5     | 556                    |
| 6     | 1216 (Baseline)        |

### Verbleibende Fehlerklassen in der Baseline

| Anzahl | Identifier                  | Beschreibung                                                        |
| -----: | --------------------------- | ------------------------------------------------------------------- |
|    301 | `missingType.return`        | Fehlende Rückgabetypen (v.a. Filament-Resources, Services)          |
|    185 | `property.notFound`         | Zugriffe auf dynamische Eloquent-Attribute ohne `@property`-Docblock |
|    161 | `missingType.generics`      | `Builder`/`Collection` ohne Generic-Parameter                        |
|    132 | `missingType.iterableValue` | `array` ohne Array-Shape bzw. Value-Typ                              |
|    107 | `argument.type`             | Zu weite oder falsche Argumenttypen                                  |
|     43 | `missingType.property`      | Properties ohne Typ                                                  |
|     31 | `method.notFound`           | Magische bzw. per Macro registrierte Methoden                        |
|     18 | `missingType.parameter`     | Parameter ohne Typ                                                   |
|     17 | `nullsafe.neverNull`        | Überflüssige `?->`-Zugriffe                                          |
|     12 | `return.type`               | Rückgabewert passt nicht zur Signatur                                |
|    ~20 | Sonstige                    | u.a. `variable.undefined`, `larastan.relationExistence`              |

### Abbauplan

1. **Sofort wirksam:** Die Baseline darf nur schrumpfen. Neue Einträge sind im Review
   zu begründen, sonst ist der Fehler zu beheben.
2. **Größter Hebel:** `missingType.return` und die übrigen `missingType.*` lassen sich
   modulweise abarbeiten (ein Verzeichnis pro PR); `property.notFound` verschwindet
   größtenteils durch `@property`-Docblocks auf den Models.
3. Die zwei echten Bugs aus Level 0 (siehe Einträge oben) zuerst.

## Horizon / Redis: geprüft (FB-001)

Geprüft und einsatzbereit, siehe README-Abschnitt „Queues, Horizon & Redis":

- Redis wird von `compose.yml` als Service `redis` bereitgestellt, Client ist
  `phpredis` (`REDIS_CLIENT`).
- Horizon nutzt die Redis-Verbindung `default`, Dashboard unter `/horizon`, Zugriff
  über das Gate `viewHorizon` (nur Admin-User).
- Supervisor-Konfiguration existiert für `production` und `local`.

Die offenen Punkte stehen als Einträge oben.
