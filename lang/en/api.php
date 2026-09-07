<?php

return [

    'problems' => [
        'error' => 'The request could not be processed.',
        'unauthenticated' => 'Not authenticated',
        'forbidden' => 'Missing permission',
        'not_found' => 'Not found',
        'validation_failed' => 'Invalid input',
        'rate_limit_exceeded' => 'Too many requests',
        'idempotency_key_reused' => 'Key already used with different content',
        'funnel_has_leads' => 'Funnel already has leads',
    ],

    'errors' => [
        'funnel_has_leads' => 'This funnel cannot be deleted because leads have already been created from it.',
        'idempotency_key_reused' => 'This idempotency key was already used for a request with different content. Choose a new key.',
    ],

];
