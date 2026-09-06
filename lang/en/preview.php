<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Funnel preview (FB-018)
    |--------------------------------------------------------------------------
    |
    | Texts of the page behind a signed, time-limited preview link.
    |
    */

    'title' => 'Preview: :name',
    'badge' => 'Preview',
    'status' => 'Current state: :status. This shows the draft, not the published version.',
    'hint' => 'This link is time-limited and not linked publicly. Whoever has it can see this page.',
    'step' => 'Step :position',
    'points' => ':points points',
    'results' => 'Result screens',

    'publishable' => [
        'heading' => 'Ready to publish?',
        'ready' => 'Yes - this state can be published.',
        'blocked' => 'Not yet. This is in the way:',
    ],

];
