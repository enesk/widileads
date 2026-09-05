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

Kritischer Pfad: **FB-030 → FB-031 → FB-032 → FB-054.** Diese vier Tickets bestimmen, ob Leads sauber entstehen, sauber maskiert sind und sauber verkauft werden. Sie werden nicht parallelisiert und nicht von mehreren Agenten gleichzeitig berührt.

---

# Teil 5 — Offene Entscheidungen (Enes, vor dem jeweiligen Sprint)

Jede Entscheidung trägt ein Statusfeld. Solange der Status **offen** ist, darf das
abhängige Ticket nicht begonnen werden, ohne die Annahme im Ticket zu dokumentieren.

| Nr. | Entscheidung | Fällig | Status |
|:-:|---|---|:-:|
| 1 | Guthaben oder Rechnung als Standard? | vor Sprint 5 | **offen** |
| 2 | Exklusiv- oder Mehrfachverkauf als Standard für den Pfotencheck? | vor Sprint 6 | **offen** |
| 3 | Reklamationsfrist ohne Anrufnachweis | vor Sprint 5 | **offen** |
| 4 | Pflichtangaben für Käufer: Vermittlerregister-Nummer | vor Sprint 5 | **offen** |
| 5 | Ticketformat: Überführung ins JSON-Ticketformat | auf Zuruf | **offen** |

1. **Guthaben oder Rechnung als Standard?** FB-052 sieht beides vor. Kleinere Agenturen: Guthaben per Stripe. Größere: Monatsrechnung. Wer ist die Zielgruppe zuerst? *(vor Sprint 5)*
   **Status: offen** · betrifft FB-052, FB-059
2. **Exklusiv oder Mehrfachverkauf** als Standard für den Pfotencheck? Preisunterschied? *(vor Sprint 6)*
   **Status: offen** · betrifft FB-055
3. **Reklamationsfrist** ohne Anrufnachweis: 7 Tage wie im Pflichtenheft, oder kürzer, bis FB-E7 steht? *(vor Sprint 5)*
   **Status: offen** · betrifft FB-058, `config/funnel.php`
4. **Pflichtangaben für Käufer**: Vermittlerregister-Nummer (§ 34d GewO) verpflichtend oder optional? *(vor Sprint 5)*
   **Status: offen** · betrifft FB-050
5. **Ticketformat**: Dieses Set lässt sich 1:1 in das gewohnte JSON-Ticketformat (wie LP-CALL-EPIC) überführen — auf Zuruf.
   **Status: offen**
