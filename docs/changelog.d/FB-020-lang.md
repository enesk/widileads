# FB-020 (Nachtrag) — Runtime-Texte in eigene Sprachdatei

**Was:** Der `runtime`-Block aus `lang/de|en/funnel.php` liegt jetzt in
`lang/de/runtime.php` und `lang/en/runtime.php`; angesprochen wird er als
`__('runtime.schluessel')`. Betroffen sind die Blade-View der öffentlichen Strecke, die
Regel `DialablePhoneNumber` und der zugehörige Test.

**Warum:** Neue Übersetzungsblöcke ans Ende einer gemeinsamen Datei zu hängen löst das
Konfliktproblem nicht, es verschiebt es nur — zwei Sessions verschieben dieselbe
schließende Zeile und kollidieren systematisch. Eine Datei je Thema beseitigt das,
analog zu `docs/changelog.d/`. FB-021 bis FB-027 nutzen `runtime.php` mit.

**Neue Config-Keys:** keine. **Migrationen:** keine.

Bestehende Blöcke in `funnel.php` bleiben unangetastet; umgezogen ist nur der Block aus
FB-020. `config('funnel.runtime.max_step_visit_factor')` und
`config('funnel.runtime_public.*)` sind Konfigurationswerte und davon nicht berührt.
