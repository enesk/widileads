# Backlog / Technische Schulden

Offene Punkte, die bewusst nicht im aktuellen Ticket behoben wurden.

## Larastan: Zielniveau Level 6 (FB-001)

**Ist-Zustand:** `phpstan.neon` steht auf **Level 6**. Der Bestandscode aus dem
SaaSykit-Starterkit erfuellt dieses Niveau nicht, deshalb sind die bestehenden
**1216 Fehler** in `phpstan-baseline.neon` eingefroren. Neuer und geaenderter
Code wird ab sofort vollstaendig gegen Level 6 geprueft.

Ein schrittweises Anheben ohne Baseline war nicht moeglich: der Bestandscode
scheitert bereits an **Level 0** (2 Fehler), Level 2 bringt 369 und Level 6
ueber 1200 Fehler. Ein Umbau des Bestandscodes ist laut FB-001 ausdruecklich
nicht Teil dieses Tickets, deshalb die Baseline. Die Fehleranzahl pro Level:

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
|    301 | `missingType.return`        | Fehlende Rueckgabetypen (v.a. Filament-Resources, Services)          |
|    185 | `property.notFound`         | Zugriffe auf dynamische Eloquent-Attribute ohne `@property`-Docblock |
|    161 | `missingType.generics`      | `Builder`/`Collection` ohne Generic-Parameter                        |
|    132 | `missingType.iterableValue` | `array` ohne Array-Shape bzw. Value-Typ                              |
|    107 | `argument.type`             | Zu weite oder falsche Argumenttypen                                  |
|     43 | `missingType.property`      | Properties ohne Typ                                                  |
|     31 | `method.notFound`           | Magische bzw. per Macro registrierte Methoden                        |
|     18 | `missingType.parameter`     | Parameter ohne Typ                                                   |
|     17 | `nullsafe.neverNull`        | Ueberfluessige `?->`-Zugriffe                                        |
|     12 | `return.type`               | Rueckgabewert passt nicht zur Signatur                               |
|     ~20 | Sonstige                    | u.a. `variable.undefined`, `larastan.relationExistence`             |

### Abbauplan

1. **Sofort wirksam:** Die Baseline darf nur schrumpfen. Neue Eintraege sind im
   Review zu begruenden, sonst ist der Fehler zu beheben.
2. **Zwei echte Bugs** aus Level 0, die beim Anfassen der Dateien mitbehoben
   werden sollten:
   - `app/Services/PaymentProviders/LemonSqueezy/LemonSqueezyProvider.php:181` -
     `Undefined variable: $product`.
   - `app/Services/TenantService.php:211` - Relation `tenant` existiert auf
     `App\Models\Invitation` nicht.
3. **Groesster Hebel:** `missingType.return` und `missingType.*` lassen sich
   modulweise abarbeiten (ein Verzeichnis pro PR), `property.notFound`
   verschwindet grossteils durch `@property`-Docblocks auf den Models.

## Horizon / Redis (FB-001)

Geprueft, siehe README-Abschnitt "Queues, Horizon & Redis". Offene Punkte:

- **`horizon:snapshot` ist nicht eingeplant.** Ohne
  `Schedule::command('horizon:snapshot')->everyFiveMinutes()` in
  `routes/console.php` bleibt die Metrics-Seite von Horizon dauerhaft leer.
  Bewusst nicht in FB-001 geaendert, weil es das Scheduling-Verhalten aendert.
- **`config/horizon.php` kennt nur `production` und `local`.** In einer
  `staging`-Umgebung startet Horizon keine Supervisor und verarbeitet keine
  Jobs. Vor dem ersten Staging-Deployment ergaenzen.
- **`QUEUE_CONNECTION` steht in `.env.example` auf `sync`.** Fuer den Betrieb
  mit Horizon muss `redis` gesetzt werden (im README dokumentiert).

## Uebersetzungsverzeichnis

`resources/lang/` und `lang/` existierten parallel; Laravel bevorzugt
`resources/lang/`, wodurch die Dateien unter `lang/` (inkl. der vollstaendigen
`validation.php`) wirkungslos waren. In FB-001 wurde auf `lang/` konsolidiert.
Uebersetzungen gehoeren ab sofort ausschliesslich nach `lang/<locale>/`.
