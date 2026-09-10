<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fehlerformat der Management-API (FB-030d)
    |--------------------------------------------------------------------------
    |
    | Titel und Erlaeuterung sind fuer Menschen und duerfen sich aendern. Der
    | maschinenlesbare Schluessel steht im Feld "type" der Antwort.
    |
    */

    'problems' => [
        'validation_failed' => [
            'title' => 'Eingabe fehlerhaft',
            'detail' => 'Die übermittelten Daten sind unvollständig oder ungültig.',
        ],
        'unauthenticated' => [
            'title' => 'Nicht authentifiziert',
            'detail' => 'Die Anfrage trägt kein gültiges API-Token.',
        ],
        'insufficient_ability' => [
            'title' => 'Berechtigung fehlt',
            'detail' => 'Dieses Token trägt die verlangte Berechtigung nicht.',
        ],
        'not_found' => [
            'title' => 'Nicht gefunden',
            'detail' => 'Unter dieser Adresse gibt es nichts, was dieses Token sehen darf.',
        ],
        'rate_limit' => [
            'title' => 'Zu viele Anfragen',
            'detail' => 'Das Token hat sein Anfragekontingent ausgeschöpft.',
        ],
        'unexpected' => [
            'title' => 'Unerwarteter Fehler',
            'detail' => 'Die Anfrage konnte nicht verarbeitet werden.',
        ],
        'funnel_has_leads' => [
            'title' => 'Funnel hat bereits Leads',
            'detail' => 'Dieser Funnel kann nicht gelöscht werden, weil bereits Leads daraus entstanden sind. Archiviere ihn stattdessen.',
        ],
        'idempotency_key_reused' => [
            'title' => 'Schlüssel bereits mit anderem Inhalt verwendet',
            'detail' => 'Dieser Idempotency-Key wurde bereits für eine Anfrage mit anderem Inhalt verwendet. Wähle einen neuen Schlüssel.',
        ],
    ],

];
