<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auswertungen (FB-E6)
    |--------------------------------------------------------------------------
    */

    'funnel' => [
        'heading' => 'Trichter je Funnel',
        'nav_label' => 'Auswertung',
        'funnel' => 'Funnel',
        'from' => 'Von',
        'until' => 'Bis',
        'views' => 'Aufrufe',
        'submissions' => 'Abschlüsse',
        'abandoned' => 'Abbrüche',
        'completion_rate' => 'Abschlussquote',
        'steps' => 'Schritte',
        'step' => 'Schritt',
        'step_views' => 'Gesehen',
        'step_completions' => 'Abgeschlossen',
        'drop_offs' => 'Abgesprungen',
        'drop_off_rate' => 'Absprungquote',
        'no_funnel' => 'Für diesen Workspace gibt es noch keinen Funnel.',
        'no_steps' => 'Diese Fassung hat keine Schritte - oder der Funnel ist noch nicht veröffentlicht.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Onboarding-Trichter (LP-ONBOARD-015)
    |--------------------------------------------------------------------------
    */

    'onboarding' => [
        'heading' => 'Onboarding',
        'nav_label' => 'Onboarding',
        'description' => 'Wo neue Käufer hängen bleiben - von der Registrierung bis zum ersten Anruf. Alle Zahlen kommen aus der eigenen Datenbank und werden zehn Minuten zwischengespeichert.',
        'funnel_heading' => 'Trichter',
        'funnel_hint' => 'Je Stufe die absolute Zahl und die Quote gegen die Vorstufe. Gezählt wird, wer die Stufe erreicht hat - unabhängig von den Stufen davor; nachgetragene Haken aus dem Backfill können eine Quote daher über 100 % treiben.',
        'period' => 'Zeitraum',
        'last_days' => 'Letzte :days Tage',
        'export' => 'Als CSV',
        'empty' => 'In diesem Zeitraum hat sich kein Käufer registriert.',
        'of_previous' => 'Anteil an der Vorstufe',
        'activation_rate' => 'Aktivierung in 7 Tagen',
        'activation_rate_hint' => ':activated von :registered Registrierungen haben innerhalb von :days Tagen den ersten Lead gekauft.',
        'activation_window_open' => '{1} Bei einer Registrierung läuft das Fenster noch.|[2,*] Bei :count Registrierungen läuft das Fenster noch.',
        'median' => 'Median bis zum ersten Kauf',
        'median_hint' => '{0} Noch kein erster Kauf in diesem Zeitraum.|{1} Grundlage: ein Käufer.|[2,*] Grundlage: :count Käufer.',
        'no_data' => 'Keine Daten',
        'hours' => '{1} :count Stunde|[2,*] :count Stunden',
        'days' => '{1} :count Tag|[2,*] :count Tage',
        'stuck_heading' => 'Hängt gerade',
        'stuck_hint' => 'Käufer-Workspaces, die seit mehr als :hours Stunden auf derselben Stufe stehen - ohne Zeitraum-Filter, denn gemeint sind genau die alten Fälle.',
        'stuck_empty' => 'Kein Workspace hängt länger als die Schwelle.',
        'workspace' => 'Workspace',
        'stage' => 'Steht auf',
        'next_stage' => 'Wartet auf',
        'waiting' => 'Seit',
        'contact' => 'Kontakt',
        'no_contact' => 'Kein Kontakt hinterlegt',

        'stages' => [
            'registered' => 'Registriert',
            'email_verified' => 'E-Mail bestätigt',
            'phone_verified' => 'Telefon verifiziert',
            'industries_selected' => 'Branchen',
            'region_selected' => 'Region',
            'payment_setup' => 'Zahlung',
            'first_topup' => 'Erstes Guthaben',
            'first_purchase' => 'Erster Kauf',
            'first_call' => 'Erster Anruf',
        ],

        'csv' => [
            'stage' => 'Stufe',
            'count' => 'Anzahl',
            'percent_of_previous' => 'Anteil Vorstufe (%)',
            'percent_of_total' => 'Anteil Registrierungen (%)',
        ],
    ],

];
