# Changelog-Einträge

Ein Ticket, eine Datei: `FB-###.md`. Keine Sammeldatei, deshalb keine Merge-Konflikte
zwischen parallel laufenden Tickets.

`docs/CHANGELOG.md` wird nicht mehr geändert. Die dort bereits vorhandenen Einträge
bleiben als Archiv stehen.

## Format

```markdown
# FB-### — <Ticketttitel>

**Was:** Was wurde gebaut oder geändert (ein bis drei Sätze).

**Warum:** Der fachliche Grund bzw. die Anforderung aus dem Ticket.

**Neue Config-Keys:** `config/funnel.php` → `key.pfad` (Standardwert, env-Variable) —
oder „keine".

**Migrationen:** nur nötig, wenn das Ticket Tabellen anlegt oder ändert.
```

Weitere Konventionen:

- Breaking Changes an der API mit **BREAKING** am Zeilenanfang kennzeichnen.
- Der Eintrag beschreibt das Ergebnis, nicht den Arbeitsweg.
- Der Eintrag ist Teil der Definition of Done — ein Ticket gilt ohne ihn nicht als
  fertig.
