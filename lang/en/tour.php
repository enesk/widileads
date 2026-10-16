<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Guided tours (LP-ONBOARD-013)
|--------------------------------------------------------------------------
|
| See lang/de/tour.php for the reasoning behind the wording. The placeholders
| {{current}} and {{total}} in "progress" are filled in by driver.js and have
| to stay as they are.
*/

return [
    'controls' => [
        'next' => 'Next',
        'previous' => 'Back',
        'done' => 'Done',
        'close' => 'Skip',
        'progress' => '{{current}} of {{total}}',
    ],

    'restart' => 'Restart tour',

    'marketplace' => [
        'balance' => [
            'title' => 'Your balance',
            'text' => 'This is what your balance will buy you.',
        ],
        'lead' => [
            'title' => 'The requests',
            'text' => 'Every card is a real request – you see the name and the location right away.',
        ],
        'filters' => [
            'title' => 'Narrow it down',
            'text' => 'This is where you filter the requests.',
        ],
        'purchase' => [
            'title' => 'Buy a lead',
            'text' => 'Buying only reserves the amount – you are charged after the call has been answered.',
        ],
    ],

    'lead_detail' => [
        'contact' => [
            'title' => 'The contact details',
            'text' => 'The number stays hidden, you call through widileads.',
        ],
        'call' => [
            'title' => 'Call now',
            'text' => 'One tap connects you – your phone rings first, then the prospect. They see your verified number.',
        ],
        'deadline' => [
            'title' => 'Your deadline',
            'text' => 'This is how long you have for your call attempts.',
        ],
        'history' => [
            'title' => 'Call history',
            'text' => 'Every attempt shows up here – this is your proof, too.',
        ],
    ],
];
