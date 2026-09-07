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

    /*
    |--------------------------------------------------------------------------
    | Purchase criteria (FB-051)
    |--------------------------------------------------------------------------
    */

    'profile' => [

        'heading' => 'Purchase criteria',
        'nav_label' => 'Purchase criteria',
        'description' => 'Define which leads you want to see. An empty field does not restrict anything -- without a setting you see everything.',
        'saved' => 'Purchase criteria saved.',
        'submit' => 'Save',

        'funnels' => 'Questionnaires',
        'funnels_helper' => 'Without a selection you see leads from every questionnaire.',
        'no_funnels' => 'No questionnaire is published at the moment.',

        'postal_prefixes' => 'Regions',
        'postal_prefixes_helper' => 'Beginnings of postal codes, separated by commas. "76" covers everything from 76001 to 76999.',

        'answer_filters' => 'Answer filters',
        'answer_filters_helper' => 'One field key per row plus the answers you accept. For a multiple choice question one match is enough.',
        'field_key_placeholder' => 'e.g. tierart',
        'values_placeholder' => 'e.g. hund, katze',
        'add_filter' => 'Add filter',
        'remove_filter' => 'Remove',
        'no_answer_filters' => 'No answer filter yet -- you see leads with any answers.',

        'min_score' => 'Minimum score',
        'min_score_helper' => 'Empty: no lower bound. Leads without a score drop out as soon as a value is set here.',

        'daily_limit' => 'Daily limit',
        'daily_limit_helper' => 'Maximum number of automatic purchases per day. Empty or 0: no limit.',

        'auto_buy' => 'Buy matching leads automatically',
        'auto_buy_helper' => 'With automatic buying off you see matching leads in the marketplace and decide yourself.',

        'notify_email' => 'Send notifications to',
        'notify_email_helper' => 'Without a value we use the address from your registration.',

        'validation' => [
            'too_many_prefixes' => 'At most :max regions are allowed.',
            'prefix_format' => '":prefix" is not a region: digits only, at most :max characters.',
            'notify_email' => 'Please provide a valid email address.',
            'min_score' => 'The minimum score must be a whole number.',
            'daily_limit' => 'The daily limit must be a whole number of 0 or more.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Credits (FB-052)
    |--------------------------------------------------------------------------
    */

    'credit' => [

        'type' => [
            'purchase' => 'Purchase',
            'debit' => 'Debit',
            'refund' => 'Refund',
            'adjustment' => 'Adjustment',
        ],

        'resource' => [
            'label' => 'Credit entry',
            'plural_label' => 'Credit ledger',
            'empty_heading' => 'No entries yet',
            'empty_description' => 'Entries appear here as soon as a buyer purchases or spends credits.',
            'read_only' => 'Entries are immutable. A wrong entry is corrected with a counter entry, not by overwriting it.',
        ],

        'fields' => [
            'created_at' => 'Time',
            'tenant' => 'Buyer',
            'type' => 'Type',
            'credits' => 'Credits',
            'amount_cents' => 'Amount in cents',
            'reference' => 'Reference',
        ],

        'hints' => [
            'credits' => 'A positive value credits, a negative one debits. Zero is not allowed -- an entry without effect does not belong in a ledger.',
            'amount_cents' => 'Only fill this in when actual money is behind the entry, for example for credits paid by invoice.',
        ],

        'actions' => [
            'adjust' => 'Book credits',
            'adjust_description' => 'Manual adjustment of the balance. This is also the route for credits paid by invoice: the agreed amount is booked here by hand.',
            'adjusted' => 'Entry created.',
        ],

        'errors' => [
            'not_updatable' => 'A credit entry cannot be changed once it has been created.',
            'not_deletable' => 'A credit entry cannot be deleted.',
            'insufficient' => 'Not enough credits: :balance available, :requested required.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Marketplace (FB-053)
    |--------------------------------------------------------------------------
    */

    'listing' => [

        'heading' => 'Marketplace',
        'nav_label' => 'Marketplace',
        'description' => 'Available leads matching your purchase criteria. Contact details become visible after the purchase.',
        'no_profile' => 'You have not set any purchase criteria yet -- you are therefore seeing every available lead.',
        'empty' => 'No available lead currently matches your purchase criteria.',

        'sort' => 'Sorting',
        'sort_newest' => 'Newest first',
        'sort_score' => 'Highest score first',
        'only_watchlisted' => 'Watchlist only',

        'score' => ':score points',
        'region' => 'Region',
        'email' => 'Email',
        'phone' => 'Phone',
        'result' => 'Result',
        'unknown_funnel' => 'Unknown questionnaire',
        'taken' => 'Taken',
        'masked_hint' => 'Contact details stay hidden until the purchase.',

        'watch' => 'Add to watchlist',
        'unwatch' => 'Remove from watchlist',

        'purchase' => 'Buy lead',
        'purchase_unavailable' => 'Buying is not enabled yet.',

    ],

    /*
    |--------------------------------------------------------------------------
    | Purchase (FB-054)
    |--------------------------------------------------------------------------
    */

    'purchase' => [

        'balance' => 'Credits: :credits leads',
        'confirm' => 'Buy this lead now? One credit is debited and the contact details are released.',
        'done' => 'Lead purchased. The contact details are visible now and the confirmation is on its way.',

        'errors' => [
            'already_taken' => 'This lead has been taken in the meantime. Another buyer was faster.',
            'buyer_not_approved' => 'This workspace has not been approved for the marketplace yet.',
            'own_lead' => 'This lead comes from one of your own questionnaires and cannot be bought.',
        ],

        'mail' => [
            'subject' => 'Your purchased lead',
            'heading' => 'Lead purchased',
            'intro' => 'You purchased a lead from the questionnaire ":funnel". The contact details are below.',
            'contact_heading' => 'Contact details',
            'outro' => 'Get in touch soon -- the conversion rate drops with every day that passes.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | My leads (FB-057)
    |--------------------------------------------------------------------------
    */

    'purchased' => [

        'heading' => 'My leads',
        'nav_label' => 'My leads',
        'description' => 'The leads you bought. Contact details are shown in full.',
        'empty' => 'You have not bought a lead yet.',
        'only_without_feedback' => 'Without feedback only',
        'export' => 'Download as CSV',
        'bought_at' => 'Bought on :date',
        'feedback_given' => 'Your feedback: :feedback',

        'feedback' => [
            'interested' => 'Useful',
            'not_interested' => 'Not useful',
        ],

        'csv' => [
            'purchased_at' => 'Bought on',
            'funnel' => 'Questionnaire',
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'postal_code' => 'Postal code',
            'score' => 'Score',
            'feedback' => 'Feedback',
        ],

    ],

];
