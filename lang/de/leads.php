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
        'masked_hint' => 'Die Kontaktdaten sind verdeckt. Nach dem Kauf siehst du sie vollstaendig.',
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
        'close' => 'Detail schliessen',
        'not_found' => 'Dieser Lead gehoert nicht zu diesem Workspace.',
        'answers' => 'Antworten',
        'result' => 'Ergebnis',
        'price_at_creation' => 'Preis bei Eingang',
        'duplicate_of' => 'Moegliche Dublette zu Lead #:id.',
        'origin' => 'Herkunft',
        'utm_source' => 'Quelle',
        'utm_campaign' => 'Kampagne',
        'embed_origin' => 'Eingebettet auf',
        'state_log' => 'Zustandsverlauf',
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
        'search_name_only' => 'Ohne Berechtigung fuer Kontaktdaten wird nur ueber den Namen gesucht.',
        'funnel' => 'Funnel',
        'state' => 'Zustand',
        'postal_prefix' => 'PLZ beginnt mit',
        'from' => 'Von',
        'until' => 'Bis',
        'score_min' => 'Punkte ab',
        'score_max' => 'Punkte bis',
        'score' => 'Punkte',
        'all' => 'Alle',
        'reset' => 'Filter zuruecksetzen',
        'received_at' => 'Eingegangen',
        'open' => 'Oeffnen',
        'empty' => 'Keine Leads gefunden.',
        'stale' => 'Liegenbleiber',
        'stale_hint' => 'Steht laenger als :days Tage im Marktplatz, ohne gekauft zu werden.',
    ],

];
