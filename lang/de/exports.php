<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lead-Exporte (FB-073)
    |--------------------------------------------------------------------------
    */

    'heading' => 'Exporte',
    'nav_label' => 'Export',
    'new' => 'Neuen Export anfordern',
    'hint' => 'Der Export laeuft im Hintergrund. Sobald die Datei fertig ist, erscheint hier ein befristeter Download-Link.',
    'columns_legend' => 'Spalten',
    'request' => 'Export anfordern',
    'requested_at' => 'Angefordert',
    'requested_by' => 'Von',
    'status_label' => 'Status',
    'rows' => 'Zeilen',
    'download' => 'Herunterladen',
    'empty' => 'Es wurde noch kein Export angefordert.',

    'status' => [
        'pending' => 'Wartet',
        'running' => 'Laeuft',
        'ready' => 'Fertig',
        'failed' => 'Fehlgeschlagen',
    ],

    'columns' => [
        'id' => 'Kennung',
        'created_at' => 'Eingegangen',
        'funnel' => 'Funnel',
        'lead_state' => 'Zustand',
        'score' => 'Punkte',
        'result_key' => 'Ergebnis',
        'price_at_creation' => 'Preis',
        'name' => 'Name',
        'email' => 'E-Mail',
        'phone' => 'Telefon',
        'postal_code' => 'Postleitzahl',
        'utm_source' => 'Quelle',
        'utm_campaign' => 'Kampagne',
        'embed_origin' => 'Eingebettet auf',
        'answers' => 'Antworten',
    ],

    'errors' => [
        'no_columns' => 'Waehle mindestens eine Spalte aus.',
        'no_requester' => 'Der Export laesst sich ohne anfordernden Benutzer nicht erzeugen.',
        'not_writable' => 'Die Exportdatei konnte nicht angelegt werden.',
        'failed' => 'Der Export ist abgebrochen. Versuche es erneut.',
    ],

];
