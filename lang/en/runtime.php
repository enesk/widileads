<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public funnel run (FB-020)
    |--------------------------------------------------------------------------
    |
    | Texte, die der Endkunde auf der Strecke unter /f/{token} sieht. Eigene
    | Datei je Thema, damit sich zwei parallele Tickets nicht in derselben
    | Datei begegnen.
    |
    */

    'progress' => 'Progress',
    'next' => 'Continue',
    'submit' => 'Send request',
    'continue' => 'Continue to contact details',
    'thanks_title' => 'Thank you!',
    'thanks_body' => 'We received your request. A suitable provider will get in touch.',
    'archived_title' => 'This questionnaire is no longer available',
    'archived_body' => 'The funnel has been archived and can no longer be filled in.',

    'errors' => [
        'phone_not_dialable' => 'We could not read this phone number. Please include the area code.',
    ],

];
