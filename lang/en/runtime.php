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

    'honeypot_label' => 'Please leave this field empty.',
    'progress' => 'Progress',
    'next' => 'Continue',
    'submit' => 'Send request',
    'continue' => 'Continue to contact details',
    'thanks_title' => 'Thank you!',
    'thanks_body' => 'We received your request. A suitable provider will get in touch.',
    'archived_title' => 'This questionnaire is no longer available',
    'archived_body' => 'The funnel has been archived and can no longer be filled in.',

    'errors' => [
        'rate_limited' => 'We received a lot of requests from here just now. Please try again in an hour.',
        'phone_not_dialable' => 'We could not read this phone number. Please include the area code.',
        'event_not_updatable' => 'An entry in the session log cannot be changed once it has been created.',
        'event_not_deletable' => 'An entry in the session log cannot be deleted.',
    ],

    // FB-027: for screen readers, where the asterisk alone is not enough.
    'required' => 'Required',
    'step_announcement' => 'Step :current of :total: :title',

];
