<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Error format of the management API (FB-030d)
    |--------------------------------------------------------------------------
    |
    | Title and detail are for humans and may change. The machine-readable key
    | is the "type" field of the response.
    |
    */

    'problems' => [
        'validation_failed' => [
            'title' => 'Invalid input',
            'detail' => 'The submitted data is incomplete or invalid.',
        ],
        'unauthenticated' => [
            'title' => 'Not authenticated',
            'detail' => 'The request carries no valid API token.',
        ],
        'insufficient_ability' => [
            'title' => 'Missing ability',
            'detail' => 'This token does not carry the required ability.',
        ],
        'not_found' => [
            'title' => 'Not found',
            'detail' => 'There is nothing at this address that this token may see.',
        ],
        'rate_limit' => [
            'title' => 'Too many requests',
            'detail' => 'The token has exhausted its request quota.',
        ],
        'unexpected' => [
            'title' => 'Unexpected error',
            'detail' => 'The request could not be processed.',
        ],
        'funnel_has_leads' => [
            'title' => 'Funnel already has leads',
            'detail' => 'This funnel cannot be deleted because leads have already been created from it. Archive it instead.',
        ],
    ],

];
