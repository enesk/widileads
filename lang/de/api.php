<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Management-API (FB-030b)
    |--------------------------------------------------------------------------
    |
    | Fehlertitel nach RFC 9457. Der maschinenlesbare Schluessel steht im Feld
    | "type" und aendert sich nie -- diese Texte richten sich an Menschen.
    |
    */

    'problems' => [
        'error' => 'Die Anfrage konnte nicht bearbeitet werden.',
        'unauthenticated' => 'Nicht angemeldet',
        'forbidden' => 'Berechtigung fehlt',
        'not_found' => 'Nicht gefunden',
        'validation_failed' => 'Eingabe fehlerhaft',
        'rate_limit_exceeded' => 'Zu viele Anfragen',
        'idempotency_key_reused' => 'Schluessel bereits mit anderem Inhalt verwendet',
        'funnel_has_leads' => 'Funnel hat bereits Leads',
    ],

    'errors' => [
        'funnel_has_leads' => 'Dieser Funnel kann nicht geloescht werden, weil bereits Leads daraus entstanden sind.',
        'idempotency_key_reused' => 'Dieser Idempotency-Key wurde bereits fuer eine Anfrage mit anderem Inhalt verwendet. Waehle einen neuen Schluessel.',
    ],

];
