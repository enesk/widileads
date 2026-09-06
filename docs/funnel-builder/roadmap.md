# Teil 4 — Reihenfolge und Parallelisierung

```
Sprint 1  FB-001…006, FB-040                         (Fundament)
Sprint 2  FB-010…014, FB-030 ∥ FB-030a               (Schema + Zustandsmaschine ∥ API-Spec)
Sprint 3  FB-015…017 ∥ FB-020…023, FB-031…033        (Builder ∥ Runtime + Lead-Kern)
Sprint 4  FB-024…026, FB-030b…e ∥ FB-034…038          (Embed + API ∥ Lead-Dashboard, DSGVO)
Sprint 5  FB-050…054 ∥ FB-070, FB-073                 (Marktplatz-Kern ∥ Auswertung)
Sprint 6  FB-055…060, FB-072, FB-074, FB-041…045      (Marktplatz-Ausbau, Admin, Härtung)
Phase 2   FB-080…085                                  (Anrufnachweis)
```

Phase 2 (FB-080…085, Anrufnachweis) wird **nicht gebaut** — siehe Entscheidung 6 in
Teil 5. Der Block bleibt in der Reihenfolge stehen, damit die Abhängigkeiten lesbar
bleiben, ist aber kein Lieferumfang.

Kritischer Pfad: **FB-030 → FB-031 → FB-032 → FB-054.** Diese vier Tickets bestimmen, ob Leads sauber entstehen, sauber maskiert sind und sauber verkauft werden. Sie werden nicht parallelisiert und nicht von mehreren Agenten gleichzeitig berührt.

---

# Teil 5 — Entscheidungen (Enes)

Jede Entscheidung trägt ein Statusfeld. Solange der Status **offen** ist, darf das
abhängige Ticket nicht begonnen werden, ohne die Annahme im Ticket zu dokumentieren.
Ist sie entschieden, gilt sie als gesetzt: sie wird in den betroffenen Tickets nicht
neu aufgeworfen. Die Kurzfassung steht zusätzlich in
[agent-prompt.md](agent-prompt.md), damit jeder Agent sie vor sich hat.

| Nr. | Entscheidung | Betrifft | Status |
|:-:|---|---|:-:|
| 1 | Guthaben oder Rechnung als Standard? | FB-052, FB-059 | **entschieden (2026-09-06)** |
| 2 | Exklusiv- oder Mehrfachverkauf als Standard? | FB-055 | **entschieden (2026-09-06)** |
| 3 | Reklamationsfrist ohne Anrufnachweis | FB-058, `config/funnel.php` | **entschieden (2026-09-06)** |
| 4 | Pflichtangaben für Käufer: Vermittlerregister-Nummer | FB-050 | **entschieden (2026-09-06)** |
| 5 | Ticketformat: Überführung ins JSON-Ticketformat | docs/funnel-builder/tickets.md | **entschieden (2026-09-06)** |
| 6 | Anrufnachweis FB-E7 im MVP? | FB-080…085, FB-058 | **entschieden (2026-09-06)** |

### 1. Guthaben oder Rechnung als Standard?

**Status: entschieden (2026-09-06)** · betrifft FB-052, FB-059

**Guthaben per Stripe ist der Standardweg.** Rechnung bleibt möglich, aber
ausschließlich als manuelle `adjustment`-Buchung im `credit_ledger` durch den
Plattform-Admin.

**Begründung:** Prepaid bedeutet kein Zahlungsausfall und kein Mahnprozess, und
SaasyKit deckt Stripe-Einmalkäufe bereits ab. Ein echter Rechnungslauf wäre eigene
Buchhaltungslogik, die der MVP nicht braucht.

### 2. Exklusiv- oder Mehrfachverkauf als Standard?

**Status: entschieden (2026-09-06)** · betrifft FB-055

**`exclusive` ist Default** — `funnels.sale_mode = exclusive`, `max_buyers = 1`, zum
Standardpreis 15,00 €. `shared` wird **vollständig implementiert**, aber nicht als
Default. Vorgabewerte für `shared`: `max_buyers` 3, `shared_price` 7,50 €, je Funnel
konfigurierbar und als Default in `config/funnel.php`.

**Begründung:** Agenturen zahlen für Exklusivität; Mehrfachverkauf senkt die
Abschlussquote je Käufer und treibt die Reklamationsquote.

### 3. Reklamationsfrist ohne Anrufnachweis

**Status: entschieden (2026-09-06)** · betrifft FB-058, `config/funnel.php`

**7 Tage**, wie im Pflichtenheft.

**Begründung:** deckt sich mit `call.deadline_days` (7) aus FB-004 — ein einziges
Zeitmaß für „der Käufer hatte Gelegenheit, den Lead zu erreichen". Zwei
unterschiedliche Fristen wären eine Fehlerquelle beim späteren Umstieg auf FB-E7.

### 4. Pflichtangaben für Käufer: Vermittlerregister-Nummer

**Status: entschieden (2026-09-06)** · betrifft FB-050

Die Vermittlerregister-Nummer (§ 34d GewO) ist ein **optionales Feld, keine Pflicht**.
Die AV-Vertrag-Checkbox bleibt Pflicht mit Zeitstempel.

**Begründung:** nicht jeder Käufer ist Versicherungsvermittler (Vergleichsportale,
Direktversicherer); eine harte Pflicht würde zulässige Käufer aussperren. Die
Freischaltung durch den Plattform-Admin ist ohnehin manuell, dort wird im Einzelfall
geprüft.

### 5. Ticketformat: Überführung ins JSON-Ticketformat

**Status: entschieden (2026-09-06)** · betrifft docs/funnel-builder/tickets.md

**Nein, wird nicht gemacht.** Das Ticketset bleibt als Markdown unter
`docs/funnel-builder/tickets.md` die Arbeitsgrundlage.

**Begründung:** die Tickets werden ausschließlich von Agenten-Sessions gelesen, die
Markdown direkt verarbeiten. Eine zweite, generierte Fassung wäre eine
Parallelstruktur, die auseinanderlaufen kann, ohne dass jemand etwas davon hat.

### 6. Anrufnachweis FB-E7 im MVP?

**Status: entschieden (2026-09-06)** · betrifft FB-080…085, FB-058

**Nein.** FB-080 bis FB-085 (Rufnummernbestätigung, Twilio Click-to-Call,
`call_attempts`, Bewertung, Nachweisstand, Rufnummer-Ausblendung) bleiben Phase 2 und
werden nicht gebaut.

**Begründung:** es sind die sechs teuersten Tickets des Sets und alle mit Prio K.

**Folge:** FB-058 (Reklamation) ist bis auf Weiteres der **einzige** Weg von
`verkauft` nach `erreicht` bzw. `unerreichbar` und wird deshalb vollwertig gebaut —
Operator-Prüfqueue, Gutschrift über `credit_ledger` `refund`, Reklamationsquote je
Käufer — nicht als Provisorium.
