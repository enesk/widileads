<?php

/**
 * Funnel-Builder (FB-015).
 *
 * Eigene Themendatei statt eines Blocks in funnel.php: so koennen zwei
 * parallel laufende Tickets nie dieselbe Datei anfassen.
 */
return [
    'title' => 'Builder: :funnel',
    'steps' => 'Schritte',
    'add_step' => 'Schritt',
    'new_step' => 'Neuer Schritt',
    'step_title' => 'Titel des Schritts',
    'no_steps' => 'Noch kein Schritt angelegt.',
    'select_step' => 'Waehle links einen Schritt aus.',
    'confirm_delete_step' => 'Diesen Schritt samt seiner Fragen loeschen?',
    'questions' => 'Fragen',
    'add_question' => 'Frage',
    'no_questions' => 'Dieser Schritt hat noch keine Frage.',
    'select_question' => 'Waehle in der Mitte eine Frage aus.',
    'confirm_delete_question' => 'Diese Frage loeschen?',
    'properties' => 'Eigenschaften',
    'type' => 'Fragetyp',
    'label' => 'Beschriftung',
    'field_key' => 'Feldschluessel',
    'field_key_hint' => 'Unter diesem Namen haengt die Antwort spaeter am Lead. Er wird beim Speichern vereinheitlicht.',
    'help_text' => 'Hilfetext',
    'required' => 'Pflichtfeld',
    'rendered_by' => 'Dargestellt durch die Komponente :component.',
    'options' => 'Antwortoptionen',
    'add_option' => 'Option',
    'new_option' => 'Option :position',
    'no_options' => 'Dieser Fragetyp hat keine Optionen oder es ist noch keine angelegt.',
    'confirm_delete_option' => 'Diese Option loeschen?',
    'delete' => 'Loeschen',
    'drag' => 'Zum Sortieren ziehen',
    'reload' => 'Fremden Stand laden',
    'concurrent_edit' => 'Der Datensatz wurde zwischenzeitlich an anderer Stelle geaendert. Deine Eingabe wurde nicht gespeichert, damit die fremde Aenderung nicht verloren geht.',

    // FB-017: Theme-Editor
    'theme' => [
        'title' => 'Erscheinungsbild: :funnel',
        'primary_color' => 'Primaerfarbe',
        'secondary_color' => 'Sekundaerfarbe',
        'background_color' => 'Hintergrund',
        'text_color' => 'Textfarbe',
        'font_label' => 'Schrift',
        'progress_label' => 'Fortschrittsanzeige',
        'border_radius' => 'Eckenradius',
        'logo_path' => 'Logo',
        'logo_placeholder' => 'Pfad im oeffentlichen Speicher, z. B. logos/pfotencheck.svg',
        'buttons' => 'Beschriftung der Schaltflaechen',
        'buttons_hint' => 'Leer lassen fuer die Vorgabe.',
        'save' => 'Speichern',
        'saved' => 'Gespeichert.',
        'publish_hint' => 'Das Erscheinungsbild wird erst mit der naechsten Veroeffentlichung fuer Endkunden sichtbar - die oeffentliche Strecke liest ausschliesslich veroeffentlichte Fassungen.',
        'preview' => 'Vorschau',
        'preview_hint' => 'Zeigt den Stand im Formular, nicht den gespeicherten.',
        'preview_question' => 'Welches Tier moechtest du versichern?',
        'preview_help' => 'Beispielhafte Darstellung eines Schritts mit deinen Farben und Schriften.',
        'preview_option_one' => 'Hund',
        'preview_option_two' => 'Katze',
        'preview_option_three' => 'Ein anderes Tier',
        'default_next' => 'Weiter',
        'default_back' => 'Zurueck',
        'default_submit' => 'Absenden',
        'font' => [
            'system' => 'Systemschrift',
            'inter' => 'Inter',
            'roboto' => 'Roboto',
            'open_sans' => 'Open Sans',
        ],
        'progress' => [
            'bar' => 'Balken',
            'steps' => 'Schritte',
            'none' => 'Keine Anzeige',
        ],
    ],

];
