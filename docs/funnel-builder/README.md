# Lead-Funnel-Builder mit API — Übersicht

**Projektkey:** `FB` · **Epic-Präfix:** `FB-E*` · **Ticket-Präfix:** `FB-###`

Dieses Verzeichnis enthält die verbindliche Arbeitsgrundlage für die Plattform
„Funnel Builder": den Master-Prompt für alle Agenten, das Zieldatenmodell, das
vollständige Ticketset und die Roadmap. Alle Dateien sind versionierte Doku —
sie enthalten keinen Anwendungscode.

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

## Referenzfunnel

https://pfotencheck.tierarztportal.com/ — Quiz-Funnel „Pfotencheck" auf
tierarztportal.com. Tierhalter beantworten Fragen zu Tierart, Alter und Gesundheit;
ein Ergebnis-Screen zeigt eine Empfehlung; am Ende werden die Kontaktdaten erfasst
→ Lead für Tierkrankenversicherung.

Der Pfotencheck ist die Referenz für Umfang und Aufbau des Builders und wird in
FB-019 als importierbare Seed-Vorlage abgebildet.

## Technischer Stack (Ist-Stand im Repository)

| Baustein | Version |
|---|---|
| PHP | ^8.4 |
| laravel/framework | ^13.0 |
| filament/filament | ^5.0 (nur Admin-Panel) |
| livewire/livewire | ^4.0 |
| alpinejs | ^3.13 |
| tailwindcss | ^4.1 (+ daisyUI ^5.0) |
| laravel/horizon | ^5.21 (Redis) |
| laravel/sanctum | ^4.0 |
| phpunit/phpunit | ^11.0 |
| larastan/larastan | ^3.0 (`phpstan.neon`: aktuell Level 3) |
| laravel/pint | ^1.0 |

Datenbank: MySQL 8 · Queue/Scheduler: Redis + Horizon.

Das Ursprungsdokument nennt einen abweichenden Stack (Laravel 12, Filament 4,
Livewire 3, Pest). Maßgeblich ist die Tabelle oben; die Abweichungen sind in
[agent-prompt.md](agent-prompt.md) im Abschnitt „Abweichungen vom Ursprungsdokument"
begründet.

## Dateien in diesem Verzeichnis

| Datei | Inhalt |
|---|---|
| [agent-prompt.md](agent-prompt.md) | Master-Prompt, der jedem Agenten vorangestellt wird: Architekturleitsätze, SaasyKit-Nutzung, technische Konventionen, Definition of Done. Inklusive der bestätigten Abweichungen vom Ursprungsdokument. |
| [datenmodell.md](datenmodell.md) | Verbindliches Zieldatenmodell (Tabellenbaum) und der Lead-Lebenszyklus (`LeadState`-Enum). Vor dem Anlegen jeder Tabelle zu lesen. |
| [tickets.md](tickets.md) | Vollständiges Ticketset: Epics FB-E0 bis FB-E8 mit Prio, Größe, Abhängigkeiten und ausformulierten Beschreibungen inkl. Akzeptanzkriterien. |
| [roadmap.md](roadmap.md) | Sprintreihenfolge, Parallelisierung, kritischer Pfad sowie die offenen Entscheidungen von Enes mit Statusfeld. |

Ergänzend außerhalb dieses Verzeichnisses:

| Datei | Inhalt |
|---|---|
| [../BACKLOG.md](../BACKLOG.md) | Wünsche und Ideen, die den Ticketumfang sprengen. |
| [../CHANGELOG.md](../CHANGELOG.md) | Ein Eintrag je abgeschlossenem Ticket (Teil der Definition of Done). |

## Arbeitsreihenfolge für Agenten

1. [agent-prompt.md](agent-prompt.md) lesen — die Architekturleitsätze sind nicht verhandelbar.
2. Das eigene Ticket in [tickets.md](tickets.md) vollständig lesen, inklusive Abhängigkeiten.
3. Vor jeder neuen Tabelle [datenmodell.md](datenmodell.md) prüfen.
4. Nach Abschluss einen Eintrag in [../CHANGELOG.md](../CHANGELOG.md) ergänzen.
