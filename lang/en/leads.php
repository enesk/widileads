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
    ],

];
