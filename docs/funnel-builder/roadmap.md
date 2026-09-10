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

Phase 2 (FB-080…085, Anrufnachweis) **wird gebaut** — Entscheidung 6 ist am
10.09.2026 aufgehoben (Ticket #12), siehe Teil 5. Stand: FB-080, FB-081 und FB-082
sind umgesetzt, FB-083 bis FB-085 stehen aus.

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
| 6 | Anrufnachweis FB-E7 im MVP? | FB-080…085, FB-058 | **aufgehoben (2026-09-10)** |

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

**Status: aufgehoben (2026-09-10)** · zuvor entschieden (2026-09-06) · betrifft
FB-080…085, FB-058

**Aufgehoben durch Ticket #12.** Der Anrufnachweis wird gebaut. Die ursprüngliche
Entscheidung — FB-080 bis FB-085 bleiben Phase 2 und werden nicht gebaut, weil es die
sechs teuersten Tickets des Sets sind und alle Prio K tragen — gilt nicht mehr.

**Getroffene Festlegungen:**

- **Rufnummer beim Endkunden:** angezeigt wird die bestätigte Rufnummer des
  anrufenden Käufer-Mitarbeiters, **keine** Plattformnummer.
- **Was als erreicht zählt:** ein angenommener Anruf ab **20 Sekunden**
  Gesprächsdauer gilt als erreicht und wird abgerechnet. Der Wert steht in
  `config('funnel.call.answered_after_seconds')`.
- **Verhältnis zur Reklamation:** der Anrufnachweis löst FB-058 als **regulären** Weg
  aus dem Zustand `verkauft` ab. Die Reklamation bleibt übergangsweise für
  Ausnahmefälle bestehen.

**Stand der Umsetzung:**

| Ticket | Inhalt | Stand |
|---|---|:-:|
| FB-080 | bestätigte Rufnummer | **umgesetzt** |
| FB-081 | Click-to-Call über Twilio | **umgesetzt** |
| FB-082 | Tabelle `call_attempts` samt Rohdaten, alle Twilio-Rückrufe signaturgeprüft | **umgesetzt** |
| FB-083 | Bewertung an einer Stelle | offen |
| FB-084 | Nachweisstand für den Käufer, Fristerinnerung | offen |
| FB-085 | Rufnummer nicht mehr im ausgelieferten HTML/JSON | offen |

**Noch offen:** ob ein Anrufbeantworter ab 20 Sekunden als erreicht zählt. Die
Erkennung (`AnsweredBy`) wird bereits mitgeschrieben.
