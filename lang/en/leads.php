<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lead display (FB-032)
    |--------------------------------------------------------------------------
    |
    | Labels of the contact block. The values themselves arrive from the
    | LeadPresenter, already masked or in clear text.
    |
    */

    'contact' => [
        'heading' => 'Contact',
        'masked_hint' => 'The contact details are hidden. You will see them in full after the purchase.',
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'postal_code' => 'Postal code',
        'unknown' => 'No name',
        'missing' => 'Not provided',
    ],

    'detail' => [
        'heading' => 'Lead #:id',
        'state' => 'State: :state',
        'close' => 'Close detail',
        'not_found' => 'This lead does not belong to this workspace.',
        'answers' => 'Answers',
        'result' => 'Result',
        'price_at_creation' => 'Price on arrival',
        'duplicate_of' => 'Possible duplicate of lead #:id.',
        'origin' => 'Origin',
        'utm_source' => 'Source',
        'utm_campaign' => 'Campaign',
        'embed_origin' => 'Embedded on',
        'state_log' => 'State history',
    ],

    'resource' => [
        'label' => 'Lead',
    ],

    'list' => [
        'heading' => 'Leads',
        'nav_label' => 'Leads',
        'filters' => 'Filters',
        'search' => 'Search',
        'search_placeholder_contacts' => 'Name, email or phone',
        'search_placeholder_name' => 'Name',
        'search_name_only' => 'Without permission for contact details the search covers the name only.',
        'funnel' => 'Funnel',
        'state' => 'State',
        'postal_prefix' => 'Postal code starts with',
        'from' => 'From',
        'until' => 'Until',
        'score_min' => 'Score from',
        'score_max' => 'Score to',
        'score' => 'Score',
        'all' => 'All',
        'reset' => 'Reset filters',
        'received_at' => 'Received',
        'open' => 'Open',
        'empty' => 'No leads found.',
        'stale' => 'Stale',
        'stale_hint' => 'Has been on the marketplace for more than :days days without being bought.',
    ],

    /*
     * FB-091: Notification to the operator about a new lead.
     */
    'notification' => [
        'mail' => [
            'subject' => 'New enquiry from :funnel',
            'heading' => 'A new enquiry has arrived',
            'intro' => 'Someone has just completed the funnel. Here are the details.',
            'contact_heading' => 'Contact',
            'answers_heading' => 'Answers from the funnel',
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'postal_code' => 'Postal code',
            'outro' => 'You can see the lead with all its details and its state in the dashboard.',
            'unknown_funnel' => 'a funnel',
            'yes' => 'Yes',
            'no' => 'No',
            'funnel_label' => 'Funnel',
            'received_at' => 'Received on :date at :time',
            'score' => ':score points',
            'cta' => 'View lead in the dashboard',
            'no_answers' => 'No further details were submitted with this lead.',
            'masked_hint' => 'The contact details are hidden because this lead does not belong to your workspace.',
        ],
    ],

];
