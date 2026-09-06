# Snapshot-Format eines veröffentlichten Funnels

Ein veröffentlichter Funnel wird als unveränderlicher JSON-Snapshot ausgeliefert
(`funnel_versions.snapshot`, FB-014). Die öffentliche Strecke liest **ausschließlich**
diesen Snapshot, nie die Live-Tabellen — sonst würde eine Änderung am Entwurf die
laufende Auslieferung verändern.

Dieses Dokument beschreibt das Format. Es entstand mit FB-012 (Verzweigungslogik),
weil der `StepResolver` es zuerst braucht; FB-013 (Ergebnisse), FB-014 (Versionierung)
und FB-020 (Runtime) bauen darauf auf.

Gelesen wird das Format über `App\Funnel\Snapshots\FunnelSnapshot::fromArray()`. Der
Resolver arbeitet nur gegen diese Value Objects — keine Eloquent-Modelle, keine
Datenbankabfragen. Dadurch ist die Auswertung ohne Datenbank testbar, und FB-014 kann
sie unverändert mit echten Snapshots füttern.

## Aufbau

```json
{
  "funnel": {
    "public_token": "01J8Z...",
    "name": "Pfotencheck",
    "lead_price": 15.00,
    "contact_step_position": 3
  },
  "steps": [
    {
      "position": 1,
      "title": "Deine Situation",
      "description": "Zwei kurze Fragen.",
      "questions": [
        {
          "field_key": "tierart",
          "type": "single_choice",
          "label": "Welches Tier?",
          "help_text": null,
          "required": true,
          "position": 1,
          "validation": {},
          "meta": {},
          "options": [
            { "value": "hund", "label": "Hund", "score": 10, "position": 1, "image_path": null },
            { "value": "katze", "label": "Katze", "score": 8, "position": 2, "image_path": null }
          ]
        }
      ]
    }
  ],
  "conditions": [
    {
      "source_field_key": "tierart",
      "operator": "equals",
      "value": ["anderes"],
      "target_step_position": 3,
      "priority": 10
    }
  ],
  "results": [
    {
      "min_score": 0,
      "max_score": 3,
      "title": "Geringes Risiko",
      "body": "…",
      "cta_label": null,
      "cta_url": null,
      "show_contact_form": true
    }
  ]
}
```

## Regeln

- **Positionen statt IDs.** Schritte werden über `position` adressiert, Fragen über
  `field_key`. Datenbank-IDs stehen bewusst nicht im Snapshot: Er soll ohne die
  Live-Tabellen lesbar bleiben und beim Duplizieren eines Funnels (FB-018) nicht auf
  fremde Zeilen zeigen.
- **`conditions[].source_field_key`** verweist auf die Frage, deren Antwort geprüft
  wird, **`target_step_position`** auf den Schritt, zu dem gesprungen wird.
- **`conditions[].value`** ist der Vergleichswert. Für `in` eine Liste, für `answered`
  ohne Bedeutung (darf `null` sein), für `score_gte` eine Zahl.
- **Sortierung ist nicht garantiert.** Schritte, Fragen, Optionen und Regeln werden
  beim Lesen nach `position` bzw. `priority` sortiert; der Snapshot darf sie in
  beliebiger Reihenfolge enthalten.
- **Unbekannte Felder werden ignoriert.** Ein Snapshot aus einer späteren Version darf
  zusätzliche Schlüssel tragen, ohne dass das Lesen bricht.
- **`results[]`** trägt die Ergebnis-Screens mit ihrem Punktebereich. `min_score` und
  `max_score` sind **beidseitig einschließend**: 4 bis 7 deckt 4, 5, 6 und 7 ab.
  Ausgewertet wird der Abschnitt seit FB-013 vom `ResultResolver`; die Bereiche prüft
  der `ResultRangeValidator` auf Lücken und Überschneidungen.
- **`questions[].options[].score`** trägt die Punkte einer Antwortoption. Bei einer
  Mehrfachauswahl summieren sich die Punkte aller angekreuzten Optionen; Fragen ohne
  Optionen (Freitext, Zahl, Kontaktfelder) tragen nichts bei. Eine Option ohne `score`
  zählt null.

## Antworten

Die Antworten des Endkunden werden als flaches Array `field_key => Wert` übergeben:

```php
['tierart' => 'hund', 'alter_in_jahren' => 7, 'vorerkrankungen' => ['huefte', 'augen']]
```

Ein fehlender Schlüssel bedeutet „nicht beantwortet"; `null` und der leere String
gelten ebenfalls als unbeantwortet.

## Auswertung

| Baustein | Aufgabe | Ticket |
|---|---|---|
| `StepResolver` | nächster Schritt aus Verzweigungsregeln und Priorität | FB-012 |
| `ScoreCalculator` | Punktzahl aus den gewählten Optionen | FB-013 |
| `ResultResolver` | Ergebnis-Screen zur Punktzahl | FB-013 |
| `ResultRangeValidator` | Ergebnisbereiche lückenlos und überschneidungsfrei | FB-013 |

Alle vier arbeiten ausschließlich auf den Value Objects dieses Formats — keine
Datenbank, keine Eloquent-Modelle. Wo Punktzahl und Ergebnis einer Einreichung
gespeichert werden (`leads.score`, `leads.result_id`), entscheidet FB-031.
