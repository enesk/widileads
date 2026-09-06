<?php

/*
|--------------------------------------------------------------------------
| Lead marketplace (FB-E5)
|--------------------------------------------------------------------------
|
| A translation file per topic instead of another block in funnel.php: two
| tickets appending to the same collection file collide systematically -- one
| file per responsibility never does.
|
| Referenced as __('marketplace.<key>'). The marketplace tickets FB-051 to
| FB-060 add their own sections next to 'buyer'.
|
*/

return [

    'buyer' => [

        'status' => [
            'pending' => 'Under review',
            'active' => 'Approved',
            'rejected' => 'Rejected',
        ],

        'not_approved' => 'This workspace has not been approved for the marketplace yet.',

        'form' => [
            'heading' => 'Register as a buyer',
            'description' => 'After you submit, we review your details and unlock access to the lead marketplace.',
            'company_name' => 'Company',
            'contact_name' => 'Contact person',
            'contact_email' => 'Contact email address',
            'contact_phone' => 'Phone (optional)',
            'broker_register_number' => 'Insurance intermediary register no. (optional)',
            'broker_register_number_helper' => 'Registration number under section 34d GewO, if you have one. Buyers without an intermediary licence leave this empty.',
            'vat_id' => 'VAT identification number',
            'av_accepted' => 'I confirm the data processing agreement.',
            'av_accepted_helper' => 'Without this confirmation we may not pass any personal lead data on to you.',
            'submit' => 'Submit registration',
            'already_registered' => 'A registration already exists for this account.',
            'received_heading' => 'Registration received',
            'received_text' => 'We have received the registration for ":company".',
            'received_hint' => 'Once your access is approved, you can reach the lead marketplace. Until then no leads are visible.',
        ],

        'validation' => [
            'required' => 'Please fill in the ":attribute" field.',
            'email' => 'Please provide a valid email address for ":attribute".',
            'max' => 'The ":attribute" field is too long.',
            'vat_id' => 'The VAT identification number starts with the country code, e.g. DE123456789.',
            'av_accepted' => 'Without confirming the data processing agreement we cannot set up your access.',
        ],

        'resource' => [
            'label' => 'Buyer registration',
            'plural_label' => 'Buyer registrations',
            'empty_heading' => 'No registrations yet',
            'empty_description' => 'Registrations appear here as soon as a buyer signs up.',
            'read_only' => 'These details come from the buyer and are not edited. Approving and rejecting are the available actions.',
        ],

        'fields' => [
            'company_name' => 'Company',
            'status' => 'Status',
            'contact_name' => 'Contact person',
            'contact_email' => 'Email',
            'contact_phone' => 'Phone',
            'vat_id' => 'VAT ID',
            'broker_register_number' => 'Intermediary register no.',
            'av_accepted_at' => 'Data processing agreement confirmed at',
            'tenant' => 'Workspace',
            'created_at' => 'Received at',
            'reviewed_by' => 'Decided by',
            'reviewed_at' => 'Decided at',
            'rejection_reason' => 'Reason for rejection',
        ],

        'hints' => [
            'broker_register_number' => 'Optional: not every buyer is an insurance intermediary. Review case by case when in doubt.',
            'rejection_reason' => 'At least 10 characters. The reason is shown to the buyer.',
        ],

        'actions' => [
            'approve' => 'Approve',
            'approve_confirm' => 'This grants the buyer access to the lead marketplace.',
            'approved' => 'Buyer has been approved.',
            'reject' => 'Reject',
            'rejected' => 'Buyer has been rejected.',
        ],

    ],

];
