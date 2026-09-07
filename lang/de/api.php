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
            'detail' => 'Die uebermittelten Daten sind unvollstaendig oder ungueltig.',
        ],
        'unauthenticated' => [
            'title' => 'Nicht authentifiziert',
            'detail' => 'Die Anfrage traegt kein gueltiges API-Token.',
        ],
        'insufficient_ability' => [
            'title' => 'Berechtigung fehlt',
            'detail' => 'Dieses Token traegt die verlangte Berechtigung nicht.',
        ],
        'not_found' => [
            'title' => 'Nicht gefunden',
            'detail' => 'Unter dieser Adresse gibt es nichts, was dieses Token sehen darf.',
        ],
        'rate_limit' => [
            'title' => 'Zu viele Anfragen',
            'detail' => 'Das Token hat sein Anfragekontingent ausgeschoepft.',
        ],
        'unexpected' => [
            'title' => 'Unerwarteter Fehler',
            'detail' => 'Die Anfrage konnte nicht verarbeitet werden.',
        ],
        'funnel_has_leads' => [
            'title' => 'Funnel hat bereits Leads',
            'detail' => 'Dieser Funnel kann nicht geloescht werden, weil bereits Leads daraus entstanden sind. Archiviere ihn stattdessen.',
        ],
        'idempotency_key_reused' => [
            'title' => 'Schluessel bereits mit anderem Inhalt verwendet',
            'detail' => 'Dieser Idempotency-Key wurde bereits fuer eine Anfrage mit anderem Inhalt verwendet. Waehle einen neuen Schluessel.',
        ],
    ],

];
