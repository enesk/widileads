<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Oeffentliche Funnel-Strecke (FB-020)
    |--------------------------------------------------------------------------
    |
    | Texte, die der Endkunde auf der Strecke unter /f/{token} sieht. Eigene
    | Datei je Thema, damit sich zwei parallele Tickets nicht in derselben
    | Datei begegnen.
    |
    */

    'honeypot_label' => 'Dieses Feld bitte frei lassen.',
    'progress' => 'Fortschritt',
    'next' => 'Weiter',
    'submit' => 'Anfrage absenden',
    'continue' => 'Weiter zu den Kontaktdaten',
    'thanks_title' => 'Vielen Dank!',
    'thanks_body' => 'Deine Anfrage ist eingegangen. Ein passender Anbieter meldet sich bei dir.',
    'archived_title' => 'Diese Anfrage ist nicht mehr verfügbar',
    'archived_body' => 'Der Fragebogen wurde archiviert und kann nicht mehr ausgefüllt werden.',

    'errors' => [
        'rate_limited' => 'Wir haben von hier gerade sehr viele Anfragen erhalten. Bitte versuche es in einer Stunde noch einmal.',
        'origin_not_allowed' => 'Dieser Fragebogen darf auf dieser Seite nicht eingebettet werden.',
        'phone_not_dialable' => 'Diese Telefonnummer konnten wir nicht lesen. Bitte gib sie mit Vorwahl an.',
        'event_not_updatable' => 'Ein Ereignis im Sitzungsprotokoll kann nach dem Anlegen nicht mehr geändert werden.',
        'event_not_deletable' => 'Ein Ereignis im Sitzungsprotokoll kann nicht gelöscht werden.',
    ],

    // FB-027: Fuer Screenreader, wo der Stern allein nicht genuegt.
    'required' => 'Pflichtangabe',
    'step_announcement' => 'Schritt :current von :total: :title',

];
