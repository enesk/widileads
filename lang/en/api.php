<?php

return [

    'problems' => [
        'error' => 'The request could not be processed.',
        'unauthenticated' => 'Not authenticated',
        'forbidden' => 'Missing permission',
        'not_found' => 'Not found',
        'validation_failed' => 'Invalid input',
        'rate_limit_exceeded' => 'Too many requests',
        'funnel_has_leads' => 'Funnel already has leads',
    ],

    'errors' => [
        'funnel_has_leads' => 'This funnel cannot be deleted because leads have already been created from it.',
    ],

];
