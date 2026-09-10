<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lead-Anzeige (FB-032)
    |--------------------------------------------------------------------------
    |
    | Beschriftungen des Kontaktblocks. Die Werte selbst kommen fertig
    | maskiert oder im Klartext aus dem LeadPresenter.
    |
    */

    'contact' => [
        'heading' => 'Kontakt',
        'masked_hint' => 'Die Kontaktdaten sind verdeckt. Nach dem Kauf siehst du sie vollständig.',
        'name' => 'Name',
        'email' => 'E-Mail',
        'phone' => 'Telefon',
        'postal_code' => 'Postleitzahl',
        'unknown' => 'Ohne Namen',
        'missing' => 'Nicht angegeben',
    ],

    'detail' => [
        'heading' => 'Lead #:id',
        'state' => 'Zustand: :state',
        'close' => 'Detail schließen',
        'not_found' => 'Dieser Lead gehört nicht zu diesem Workspace.',
        'answers' => 'Antworten',
        'result' => 'Ergebnis',
        'price_at_creation' => 'Preis bei Eingang',
        'duplicate_of' => 'Mögliche Dublette zu Lead #:id.',
        'origin' => 'Herkunft',
        'utm_source' => 'Quelle',
        'utm_campaign' => 'Kampagne',
        'embed_origin' => 'Eingebettet auf',
        'state_log' => 'Zustandsverlauf',
        'subheading' => 'Aus :funnel, eingegangen am :date',
        'unknown_funnel' => 'einem Fragebogen',
        'overview' => 'Überblick',
        'answers_hint' => 'Die Angaben, die der Kunde im Fragebogen gemacht hat.',
        'question' => 'Frage',
        'answer' => 'Antwort',
        'duplicate_label' => 'Dublette',
        'yes' => 'Ja',
        'no' => 'Nein',
    ],

    'resource' => [
        'label' => 'Lead',
    ],

    'list' => [
        'heading' => 'Leads',
        'nav_label' => 'Leads',
        'filters' => 'Filter',
        'search' => 'Suche',
        'search_placeholder_contacts' => 'Name, E-Mail oder Telefon',
        'search_placeholder_name' => 'Name',
        'search_name_only' => 'Ohne Berechtigung für Kontaktdaten wird nur über den Namen gesucht.',
        'funnel' => 'Funnel',
        'state' => 'Zustand',
        'postal_prefix' => 'PLZ beginnt mit',
        'from' => 'Von',
        'until' => 'Bis',
        'score_min' => 'Punkte ab',
        'score_max' => 'Punkte bis',
        'score' => 'Punkte',
        'all' => 'Alle',
        'reset' => 'Filter zurücksetzen',
        'received_at' => 'Eingegangen',
        'open' => 'Öffnen',
        'empty' => 'Keine Leads gefunden.',
        'stale' => 'Liegenbleiber',
        'stale_hint' => 'Steht länger als :days Tage im Marktplatz, ohne gekauft zu werden.',
    ],

    /*
     * FB-091: Meldung an den Betreiber ueber einen neuen Lead.
     */
    'notification' => [
        'mail' => [
            'subject' => 'Neue Anfrage aus :funnel',
            'heading' => 'Es ist eine neue Anfrage eingegangen',
            'intro' => 'Jemand hat den Fragebogen gerade abgeschlossen. Hier sind die Angaben.',
            'contact_heading' => 'Kontakt',
            'answers_heading' => 'Angaben aus dem Fragebogen',
            'name' => 'Name',
            'email' => 'E-Mail',
            'phone' => 'Telefon',
            'postal_code' => 'Postleitzahl',
            'outro' => 'Im Dashboard siehst du den Lead mit allen Angaben und seinem Zustand.',
            'unknown_funnel' => 'einem Fragebogen',
            'yes' => 'Ja',
            'no' => 'Nein',
            'funnel_label' => 'Fragebogen',
            'received_at' => 'Eingegangen am :date um :time Uhr',
            'score' => ':score Punkte',
            'cta' => 'Lead im Dashboard ansehen',
            'no_answers' => 'Zu diesem Lead wurden keine weiteren Angaben übermittelt.',
            'masked_hint' => 'Die Kontaktdaten sind verdeckt, weil dieser Lead nicht deinem Workspace gehört.',
        ],
    ],

];
