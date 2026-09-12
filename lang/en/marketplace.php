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
        'portal' => [
            'heading' => 'Buying criteria',
            'description' => 'Decide which leads you want to see. An empty field does not restrict anything.',

            'source' => [
                'title' => 'Where from',
                'subtitle' => 'Which questionnaires should the leads come from?',
                'label' => 'Questionnaires',
                'placeholder' => 'Pick more …',
                'hint' => 'Without a selection you see leads from all questionnaires.',
                'remove' => 'Remove :name',
            ],

            'region' => [
                'title' => 'Where',
                'subtitle' => 'Which regions are you looking for customers in?',
                'label' => 'Postcode ranges',
                'placeholder' => 'Postcode start, Enter',
                'hint' => '"76" covers 76001 to 76999. Empty: all of Germany.',
                'radius' => 'Radius around your location instead of postcodes',
                'radius_action' => 'Set location',
                'too_many' => 'More than :max ranges are not possible.',
                'bad_format' => 'A range is up to :max digits.',
            ],

            'answers' => [
                'title' => 'What',
                'subtitle' => 'Which answers does a lead have to carry?',
                'label' => 'Answer filters',
                'active' => ':count active',
                'field' => 'Attribute',
                'accepted' => 'Accepted answers',
                'one_is_enough' => '· one is enough',
                'add_value' => 'Answer',
                'add_filter' => 'Add filter',
                'remove_filter' => 'Remove filter',
                'remove_value' => 'Remove :value',
                'rule' => 'All filters have to match. Within one filter, a single answer is enough.',
                'no_fields' => 'No attributes are available for the selected questionnaires.',
                'pick_value' => 'Pick an answer',
            ],

            'min_score' => 'Minimum score',
            'min_score_placeholder' => 'none',
            'min_score_hint' => 'Leads without a score drop out as soon as a value is set here.',
            'max_price' => 'Price up to',
            'max_price_placeholder' => 'any',
            'max_price_hint' => 'You do not see leads above this price.',

            'auto' => [
                'title' => 'Buy automatically',
                'subtitle' => 'Should we buy matching leads for you?',
                'label' => 'Buy matching leads automatically',
                'explainer' => 'Off: you see matching leads on the marketplace and decide yourself. On: we buy as soon as a lead matches every criterion.',
                'daily_limit' => 'Daily limit',
                'daily_limit_hint' => 'Maximum automatic purchases per day. 0: unlimited.',
                'budget' => 'Weekly budget',
                'budget_placeholder' => 'no limit',
                'budget_hint' => 'Reservations count towards it.',
                'warning' => 'Automatic purchases stop as soon as the balance falls below the lead price. You get an email then.',
            ],

            'notify' => [
                'title' => 'Notification',
                'subtitle' => 'How do you hear about new matching leads?',
                'label' => 'Notify',
                'hint' => 'Without an address we use the one from your registration.',
                'when' => 'When',
                'immediate' => 'Immediately',
                'daily' => 'Daily at 8am',
                'none' => 'No email',
            ],

            'dirty' => 'Unsaved changes',
            'clean' => 'Everything saved',
            'discard' => 'Discard',
            'save' => 'Save',

            'match' => [
                'heading' => 'Currently matches',
                'count' => '{0} No lead|{1} 1 lead|[2,*] :count leads',
                'context' => 'on the marketplace · :count in the last 7 days',
                'funnels' => 'Questionnaire :names',
                'all_funnels' => 'All questionnaires',
                'prefixes' => 'Postcodes :list',
                'postcodes' => 'Postcodes',
                'all_regions' => 'All of Germany',
                'filter' => ':field :values',
                'or' => ' or ',
                'auto_on' => 'Auto-buy on',
                'auto_off' => 'Auto-buy off',
                'view' => 'View these leads',
            ],

            'tip_heading' => 'Tip',
            'tip' => 'Criteria that are too narrow are the most common reason for an empty marketplace. Start broad and refine once you see which leads pay off.',
        ],

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
    | Marketplace (FB-053)
    |--------------------------------------------------------------------------
    */

    'orders' => [
        'heading' => 'Orders',
        'search' => 'Search orders',
        'search_placeholder' => 'Number or amount',
        'year' => 'Year',
        'all_years' => 'All years',
        'method_unknown' => 'Payment method unknown',
        'unknown_month' => 'Without date',
        'count_line' => '{0} No orders|[1,*] :count of :total orders',
        'load_more' => '{1} Load 1 more|[2,*] Load :count more',
        'footnote' => 'Every order is a balance top-up. What you paid for individual leads is in the',
        'footnote_link' => 'balance history.',
        'stats' => [
            'year' => 'This year',
            'count' => 'Orders',
            'count_short' => 'Count',
            'pending' => 'Pending',
            'pending_short' => 'Open',
        ],
        'tabs' => [
            'label' => 'Status',
            'all' => 'All',
            'paid' => 'Paid',
            'pending' => 'Pending',
            'failed' => 'Failed',
        ],
        'status' => [
            'paid' => 'Paid',
            'pending' => 'Pending',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            'disputed' => 'Disputed',
            'other' => 'Open',
        ],
        'actions' => [
            'invoice' => 'Invoice',
            'complete' => 'Complete payment',
            'complete_short' => 'Complete',
            'retry' => 'Try again',
            'retry_short' => 'Retry',
        ],
        'empty' => [
            'title' => 'Nothing found',
            'text' => 'There are no orders with this status.',
        ],
    ],

    'listing' => [
        'fits_count' => '{0} No lead matches|{1} 1 lead matches|[2,*] :count leads match',
        'contact_after_purchase' => 'Contact after purchase',
        'purchase_short' => 'Buy',
        'purchase_now' => 'Buy now',
        'load_more' => '{1} Load 1 more|[2,*] Load :count more',
        'auto_top' => 'New leads appear at the top automatically.',
        'sheet_hint' => 'You see name, phone and email after the purchase. It is only billed once a call is answered.',

        'result_count' => '{0} No lead matches your criteria|{1} 1 lead matches your criteria|[2,*] :count leads match your criteria',
        'empty_hint' => 'New enquiries appear here automatically. Widen your criteria to see more leads.',
        'no_funds' => 'Your balance is used up. Top up to buy leads.',
        'price_exceeds_balance' => 'Your available balance is not enough for this lead.',
        'badge_new' => 'New',
        'locked_contact' => 'Phone and email after the purchase',
        'price' => 'Price: :amount',
        'yesterday' => 'yesterday, :time',
        'gone' => 'Someone else just bought this lead.',
        'show_less' => 'Show less',
        'show_more' => '{1} +1 more detail|[2,*] +:count more details',

        'sort' => [
            'label' => 'Sorting',
            'newest' => 'Newest first',
            'oldest' => 'Oldest first',
            'score' => 'Highest score first',
        ],

        'filters' => [
            'remove_chip' => 'Remove filter :filter',
            'industry' => 'Industry',
            'region' => 'Region',
            'max_price' => 'Price up to',
            'all' => 'All',
            'reset' => 'Reset filters',
            'region_option' => ':group…',
            'price_option' => 'up to :amount',
        ],

        'heading' => 'Marketplace',
        'nav_label' => 'Marketplace',
        'description' => 'Available leads matching your purchase criteria. Contact details become visible after the purchase.',
        'no_profile' => 'You have not set any purchase criteria yet -- you are therefore seeing every available lead.',
        'empty' => 'No available lead currently matches your purchase criteria.',

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

        'balance' => 'Available balance: :amount',
        'confirm' => 'Buy this lead now? The price is reserved from your balance and the contact details are released.',
        'done' => 'Lead purchased. The contact details are visible now and the confirmation is on its way.',

        'errors' => [
            'already_taken' => 'This lead has been taken in the meantime. Another buyer was faster.',
            'buyer_not_approved' => 'This workspace has not been approved for the marketplace yet.',
            'already_bought' => 'You have already bought this lead.',
            'own_lead' => 'This lead comes from one of your own questionnaires and cannot be bought.',
            'price_changed' => 'The price has changed, please check the lead again.',
        ],

        'mail' => [
            'subject' => 'Your purchased lead',
            'label' => 'Lead purchase',
            'funnel_label' => 'Questionnaire',
            'heading' => 'Lead purchased',
            'intro' => 'You purchased a lead from the questionnaire ":funnel". The contact details are below.',
            'contact_heading' => 'Contact details',
            'phone_note' => 'For data protection reasons the phone number is not part of this email. Use the "Call" button in the portal to reach the lead; the number is released once the lead is billable.',
            'cta' => 'Open lead in the portal',
            'outro' => 'Get in touch soon -- the conversion rate drops with every day that passes.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Sale mode (FB-055)
    |--------------------------------------------------------------------------
    */

    'sale_mode' => [
        'exclusive' => 'Exclusive',
        'shared' => 'Shared',
        'buyers' => ':buyers of :max buyers',
    ],

    /*
    |--------------------------------------------------------------------------
    | My leads (FB-057)
    |--------------------------------------------------------------------------
    */

    'purchased' => [

        'result_count' => '{0} No purchased leads|{1} 1 purchased lead|[2,*] :count purchased leads',

        'sort' => [
            'deadline' => 'Deadline first',
            'newest' => 'Most recently purchased first',
            'oldest' => 'Earliest purchase first',
        ],
        'to_marketplace' => 'To the marketplace',
        'search' => 'Search leads',
        'search_placeholder' => 'Search name or place',
        'bought_today' => 'bought today, :time',
        'bought_yesterday' => 'bought yesterday, :time',
        'bought_on' => 'bought :date',
        'subtitle' => 'Sorted by what is up next.',
        'masking_hint' => 'Phone numbers become visible after the first conversation. Until then you call via "Call".',
        'count_line' => '{1} 1 of :total leads|[2,*] :count of :total leads',
        'show_older' => 'Show older',
        'open_row' => 'Open :name',
        'attempts_label' => ':done of :total attempts',
        'call' => 'Call',
        'number' => 'Number',
        'details' => 'Details',
        'groups' => [
            'today' => [
                'title' => 'Due today',
                'hint' => '{1} 1 lead · otherwise it is billed|[2,*] :count leads · otherwise they are billed',
            ],
            'week' => [
                'title' => 'This week',
                'hint' => '{1} 1 lead · deadline running|[2,*] :count leads · deadline running',
            ],
            'done' => [
                'title' => 'Closed',
                'hint' => '{1} 1 lead|[2,*] :count leads',
            ],
        ],
        'settlement' => [
            'billed' => ':amount billed',
            'released' => 'not billed',
        ],
        'tabs' => [
            'label' => 'Status',
            'all' => 'All',
            'open' => 'Open',
            'reached' => 'Reached',
            'unreached' => 'Not reached',
        ],
        'badge' => [
            'today_at' => 'today :time',
            'days_left' => '{1} 1 day left|[2,*] :days days left',
            'reached_on' => 'Reached :date',
            'unreached_on' => 'Not reached · :date',
            'deadline_running' => 'Deadline running',
            'deadline_today' => 'Deadline ends today',
            'reached' => 'Reached',
            'unreached' => 'Not reached',
        ],
        'deadline_notice' => [
            'count' => '{1} 1 deadline ends today.|[2,*] :count deadlines end today.',
            'text' => ':name still needs one call attempt, otherwise the lead is billed.',
        ],
        'line' => [
            'open' => ':count of :required attempts · :next · :deadline',
            'billable' => 'Conversation :duration min on :date · :amount billed',
            'billable_no_talk' => 'Deadline passed · :amount billed',
            'unreachable' => ':count of :required attempts · not billed · :amount released',
            'next_now' => 'next attempt possible now',
            'next_at' => 'next attempt from :time',
            'deadline_today' => 'deadline ends today, :time',
            'deadline_days' => 'deadline ends in :days days',
            'deadline_over' => 'deadline passed',
            'deadline_none' => 'no deadline set',
        ],
        'empty_state' => [
            'all' => [
                'title' => 'Nothing here yet',
                'text' => 'As soon as you buy a lead it shows up here.',
            ],
            'open' => [
                'title' => 'No open deadlines',
                'text' => 'All purchased leads are settled.',
            ],
            'reached' => [
                'title' => 'Nobody reached yet',
                'text' => 'Once a conversation lasts long enough, the lead lands here.',
            ],
            'unreached' => [
                'title' => 'All reached',
                'text' => 'No lead had to be released so far.',
            ],
            'search' => [
                'title' => 'Nothing found',
                'text' => 'No purchased lead matches this search. Try another name or postal code.',
            ],
        ],
        'detail' => [
            'subheading' => 'From :funnel, purchased on :date',
            'facts' => 'Purchase',
            'back' => 'Back to my leads',
            'open' => 'Open',
            'no_answers' => 'No further details were submitted with this lead.',
            'price' => 'Purchase price',

            'price_status' => [
                'reserved' => 'Reserved. It is only charged once reachability is confirmed.',
                'captured' => 'Charged.',
                'released' => 'Released. Nothing was charged.',
                'refunded' => 'Refunded.',
            ],
            'feedback_saved' => 'Feedback saved.',
        ],

        'heading' => 'My leads',
        'nav_label' => 'My leads',
        'description' => 'The leads you bought. Contact details are shown in full.',
        'empty' => 'You have not bought a lead yet.',
        'only_without_feedback' => 'Without feedback only',
        'export' => 'Download as CSV',
        'bought_at' => 'Bought on :date',
        'feedback_given' => 'Your feedback: :feedback',

        'status' => [
            'label' => 'Status',
            'hint' => 'For your overview only. It does not affect billing.',
            'open' => 'Open',
            'appointment' => 'Appointment booked',
            'offer_sent' => 'Offer sent',
            'no_demand' => 'No demand',
        ],

        'notes' => [
            'heading' => 'Your notes',
            'placeholder' => 'What happened on the last contact? What is next?',
            'hint' => 'Only you and your workspace see these notes.',
            'saving' => 'Saving …',
            'saved' => 'Saved',
        ],

        'portal' => [
            'back' => 'My leads',
            'contact' => 'Contact',
            'request' => 'Request',
            'request_received' => 'Received on :date via :funnel',
            'no_request_text' => 'No free text was submitted with this request.',
            'attributes' => 'Attributes',
            'no_attributes' => 'No further details were submitted for this lead.',
            'email_action' => 'Write an email',
            'email_hint' => 'Emails do not count as reachability. Only a phone conversation counts for billing.',
            'phone_hint_masked' => 'Hidden until billing. Use "Call now", the lead sees our portal number.',
            'meta' => 'From :funnel · purchased on :date',

            'deadline' => [
                'heading' => 'Deadline and billing',
                'ends' => 'Ends on :date, :time',
                'ended' => 'Deadline passed',
                'none' => 'No deadline set',
                'today' => 'Deadline ends today',
                'today_word' => 'today',
                'attempts' => 'Attempts',
                'attempts_value' => ':done of :total',
                'next' => 'Next attempt',
                'next_now' => 'possible now',
                'next_at' => 'from :time',
                'price' => 'Price',
                'price_reserved' => ':amount reserved',
                'consequence' => ':count more attempt without a conversation and the lead counts as unreachable, then :amount are released. If the deadline passes without that attempt, the lead is billed.|:count more attempts without a conversation and the lead counts as unreachable, then :amount are released. If the deadline passes without those attempts, the lead is billed.',
                'settled' => 'It has already been decided what happens to the money for this lead.',
                'call' => 'Call now',
            ],

            'calls' => [
                'heading' => 'Calls',
                'when_today' => 'today, :time',
                'when_yesterday' => 'yesterday, :time',
                'when_on' => ':date, :time',
                'duration' => ':duration min',
                'how_billing_works' => 'How billing works',

                'empty' => 'No attempt yet.',
                'not_counted' => 'does not count: :reason',
            ],

            'facts' => [
                'heading' => 'Purchase details',
                'purchased_at' => 'Purchased',
                'lead_number' => 'Lead no.',
                'source' => 'Source',
                'workspace' => 'Workspace',
                'complaint' => 'Report a problem with this lead',
            ],
        ],

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

    /*
    |--------------------------------------------------------------------------
    | Complaint (FB-058)
    |--------------------------------------------------------------------------
    */

    'complaint' => [

        'refund_reason' => 'Approved complaint.',

        'mail' => [
            'subject' => 'Complaint about lead #:lead',
            'label' => 'Complaint',
            'heading' => 'A buyer has filed a complaint',
            'intro' => 'A buyer is asking for this lead to be moved to a different state. If the complaint is approved, the price is refunded to their balance.',
            'filed_at' => 'Filed on :date at :time',
            'facts_heading' => 'The request',
            'lead' => 'Lead',
            'buyer' => 'Buyer',
            'cta' => 'Review complaint',
            'outro' => 'A person decides on this request. Until then the lead stays as it is.',
        ],

        'open' => 'Report this lead',
        'help' => 'Request that this lead counts as unreachable or invalid. A member of staff reviews the request; if it is accepted, the price is refunded to your balance.',
        'reason_placeholder' => 'What happened? For example: called three times on different days, nobody reachable.',
        'submit' => 'Submit request',
        'filed' => 'Complaint submitted (:state) -- status: :status.',

        'status' => [
            'pending' => 'Under review',
            'approved' => 'Accepted',
            'rejected' => 'Rejected',
        ],

        'errors' => [
            'not_your_purchase' => 'This purchase does not belong to your workspace.',
            'unsupported_state' => 'Only "unreachable" or "invalid" can be requested.',
            'reason_required' => 'Please describe what happened -- without a reason the request cannot be reviewed.',
            'lead_already_settled' => 'This lead is already settled and can no longer be reported.',
            'deadline_elapsed' => 'The complaint period for this lead has elapsed.',
            'already_filed' => 'A complaint for this purchase already exists.',
            'already_decided' => 'This complaint has already been decided.',
        ],

        'resource' => [
            'label' => 'Complaint',
            'plural_label' => 'Complaints',
            'empty_heading' => 'No complaints',
            'empty_description' => 'Requests appear here as soon as a buyer reports a lead.',
            'read_only' => 'The request comes from the buyer and is not edited. Accepting and rejecting are the available actions.',
        ],

        'fields' => [
            'created_at' => 'Received at',
            'status' => 'Status',
            'buyer' => 'Buyer',
            'requested_state' => 'Requested',
            'reason' => 'Reason',
            'reviewed_by' => 'Decided by',
            'reviewed_at' => 'Decided at',
            'decision_note' => 'Decision note',
        ],

        'hints' => [
            'decision_note' => 'At least 10 characters. The note explains the rejection.',
        ],

        'actions' => [
            'approve' => 'Accept',
            'approve_confirm' => 'The lead moves to the requested state and the price is refunded to the buyer.',
            'approved' => 'Complaint accepted, price refunded.',
            'reject' => 'Reject',
            'rejected' => 'Complaint rejected.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Billing (FB-059)
    |--------------------------------------------------------------------------
    */

    'billing' => [

        'heading' => 'Billing',
        'nav_label' => 'Billing',
        'description' => 'What a buyer received in a month and what became of it. Payment is prepaid from the wallet -- nothing is charged here, this is an account of what happened.',
        'buyer' => 'Buyer',
        'month' => 'Month',
        'no_buyer' => 'No buyer has been created yet.',
        'export' => 'Download as CSV',

        'leads' => 'Leads by state',
        'money' => 'Wallet and revenue',
        'purchases' => 'Purchases in total',
        'revenue' => 'Revenue',
        'topped_up' => 'Wallet topped up',
        'captured' => 'Captured for leads',
        'refunded' => 'Refunded',
        'captured_expected' => 'Captured purchases per receipts',
        'money_hint' => 'The captured amount and the sum of the purchase prices settled in this period have to match. If they differ, wallet and purchase flow have drifted apart.',

        'invoices' => 'Invoices',
        'invoices_hint' => 'The invoices for this month\'s wallet top-ups. SaaSykit issued them at checkout.',
        'no_invoices' => 'The wallet was not topped up in this month.',

        'csv' => [
            'buyer' => 'Buyer',
            'month' => 'Month',
            'state' => 'State',
            'count' => 'Count',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Buyer overview (FB-060)
    |--------------------------------------------------------------------------
    */

    'overview' => [

        'heading' => 'Buyers',
        'nav_label' => 'Buyers',
        'description' => 'Revenue and complaint rate per buyer. The average is :average percent; anyone more than :points percentage points above it is flagged.',
        'empty' => 'No buyer has been created yet.',

        'buyer' => 'Buyer',
        'purchases' => 'Purchases',
        'revenue' => 'Revenue',
        'complaints' => 'Accepted complaints',
        'rate' => 'Rate',
        'deviation' => 'Deviation in points',
        'flagged' => 'Flagged',

        'hint' => 'Flagged does not mean at fault: a high rate can mean a buyer received poor leads -- or that they complain too readily. The overview only says where it is worth looking.',

    ],

    /*
    |--------------------------------------------------------------------------
    | Wallet and ledger (LP-WALLET)
    |--------------------------------------------------------------------------
    */

    'wallet' => [

        /*
        |----------------------------------------------------------------------
        | Receipt for a postpaid settlement (LP-POSTPAID-015)
        |----------------------------------------------------------------------
        */

        'settlement_invoice' => [

            'title' => 'Settlement',
            'status_paid' => 'Paid',
            'link' => 'Receipt',
            'action' => 'Download receipt :reference',
            'period' => ':start to :end',
            'period_until' => 'until :end',
            'method_unknown' => 'Stored payment method',

            'fields' => [
                'period' => 'Billing period',
                'method' => 'Payment method',
            ],

            'items' => [
                'lead' => 'Lead #:lead',
                'funnel' => 'Funnel: :funnel',
                'captured_at' => 'settled on :date',
                'split' => 'Lead price :price plus surcharge :surcharge',
                'refund' => 'Refund',
                'carried_over' => 'Carried over from the previous billing period',
                'credited' => 'Credit applied',
                'balance_hint' => 'Difference between the listed items and the amount collected',
            ],

            'notes' => [
                'paid' => 'The amount was collected on :date via :method. No further action is required.',
                'vat' => 'All amounts are gross amounts and include :percent % VAT.',
            ],

        ],

        /*
        |----------------------------------------------------------------------
        | SEPA pre-notification before a postpaid settlement (LP-POSTPAID-014)
        |----------------------------------------------------------------------
        */

        /*
        |----------------------------------------------------------------------
        | Postpaid settlement notices (LP-POSTPAID-008)
        |----------------------------------------------------------------------
        */

        'settlement_notice' => [

            'label' => 'Pay as you go',
            'amount_label' => 'Amount',
            'date_label' => 'Received on',
            'method_label' => 'Payment method',
            'method_unknown' => 'No longer on file',
            'invoice_label' => 'Document',
            'payment_method_cta' => 'Check payment method',

            'paid' => [
                'subject' => 'Payment of :amount received',
                'heading' => 'Your payment has been received',
                'intro' => 'We charged :amount to your stored payment method. Your open amount is settled.',
                'invoice_cta' => 'View document',
                'outro' => 'The document stays available in your portal under settlements.',
            ],

            'failed_retry' => [
                'subject' => 'Charge of :amount failed',
                'heading' => 'The charge failed',
                'intro' => 'We could not collect :amount. We will try again on :date – please check your payment method before then.',
                'date_label' => 'Next attempt',
                'outro' => 'If the second attempt fails as well, pay as you go is suspended and fees apply.',
            ],

            'failed_final' => [
                'subject' => 'Charge of :amount failed for good',
                'heading' => 'The charge failed for good',
                'intro' => 'The second attempt to collect :amount failed as well. Pay as you go is suspended for now; the open amount of :amount remains.',
                'outro' => 'Add a new payment method and settle the open amount, then we will re-enable pay as you go.',
            ],

            'error' => [
                'subject' => 'Settlement #:settlement failed technically',
                'heading' => 'Settlement could not be started',
                'intro' => 'The charge failed after three attempts due to a technical error, not a declined payment method. The buyer was neither downgraded nor notified.',
                'settlement_label' => 'Settlement',
                'wallet_label' => 'Wallet',
                'reason_label' => 'Error',
                'outro' => 'The claim remains open and has to be restarted manually in the admin panel.',
            ],

        ],

        /*
        |----------------------------------------------------------------------
        | Kaufsperre aufgehoben (LP-POSTPAID-009)
        |----------------------------------------------------------------------
        */
        'unblocked' => [

            'mail' => [
                'subject' => 'Your account is unlocked again',
                'label' => 'Balance',
                'heading' => 'Your account is unlocked again',
                'intro' => 'Your open amount is settled. Your balance is :balance and you can buy leads again right away.',
                'cta' => 'Go to the marketplace',
                'outro' => 'Pay as you go stays disabled for now. You can apply for it again at any time.',
            ],

        ],

        'prenotification' => [

            'mail' => [
                'subject' => 'Advance notice: we will debit :amount on :date',
                'label' => 'SEPA Direct Debit',
                'heading' => 'Advance notice of your debit',
                'intro' => 'We will collect :amount from your account by SEPA Direct Debit on :date. We send this notice before every debit so you can match it to your mandate.',
                'amount_label' => 'Amount',
                'date_label' => 'Debit date',
                'iban_label' => 'Account',
                'mandate_label' => 'Mandate reference',
                'mandate_unknown' => 'Provided with the debit',
                'creditor_label' => 'Creditor',
                'coverage_hint' => 'Please make sure the account is funded by then. A returned debit incurs fees and temporarily suspends your pay-as-you-go access.',
                'support_hint' => 'Something wrong? Get in touch before the debit date so we can sort it out without a return.',
            ],

        ],

        /*
        |----------------------------------------------------------------------
        | Eligibility, application and approval of pay as you go (LP-POSTPAID-006)
        |----------------------------------------------------------------------
        */

        'postpaid' => [

            'eligibility' => [

                'reasons' => [
                    'no_tenant' => 'There is no workspace attached to this balance account.',
                    'purchases' => 'You have :count settled lead purchases so far, :required are required.',
                    'account_age' => 'Your account is :days days old, :required days are required.',
                    'payment_history' => 'There was a payment issue on your account within the last :days days.',
                    'previous_downgrade' => 'Your pay-as-you-go access was ended within the last :days days because of a payment issue.',
                    'purchase_blocked' => 'Your account is currently blocked from purchasing.',
                ],

            ],

            'errors' => [
                'disabled' => 'Pay as you go is currently unavailable.',
                'not_eligible' => 'You do not meet the requirements for pay as you go yet.',
                'payment_method_required' => 'Add a payment method we can collect from first.',
                'application_pending' => 'We already have your application. We will get back to you once it is reviewed.',
                'rejected_recently' => 'You can apply again in :days days.',
                'already_enabled' => 'You already buy with pay as you go.',
            ],

            'mail' => [

                'received' => [
                    'subject' => 'Pay as you go requested: :buyer',
                    'label' => 'Application',
                    'heading' => 'New pay-as-you-go application',
                    'intro' => ':buyer wants to buy leads with pay as you go. The application is waiting for your decision in the admin area.',
                    'buyer_label' => 'Buyer',
                    'applicant_label' => 'Applicant',
                    'purchases_label' => 'Settled purchases',
                    'account_age_label' => 'Account age',
                    'account_age_value' => ':days days',
                    'balance_label' => 'Balance',
                    'reference_label' => 'Application',
                    'hint' => 'The full eligibility figures are stored with the application in the admin area.',
                ],

                'approved' => [
                    'subject' => 'Pay as you go is enabled',
                    'label' => 'Approved',
                    'heading' => 'You now buy with pay as you go',
                    'intro' => 'We approved your application. You buy leads without paying upfront and we collect the open amount from your stored payment method.',
                    'credit_limit_label' => 'Your credit limit',
                    'surcharge_label' => 'Surcharge per lead',
                    'surcharge_value' => ':percent % of the lead price',
                    'settlement_label' => 'Settlement',
                    'settlement_value' => 'Weekly, and immediately from :threshold open',
                    'prenotification_hint' => 'We announce amount and debit date by email before every direct debit.',
                    'hint' => 'Please keep the account funded. If a collection fails we block purchases and move you back to prepaid.',
                ],

                'rejected' => [
                    'subject' => 'Your pay-as-you-go application',
                    'label' => 'Application',
                    'heading' => 'We cannot enable pay as you go yet',
                    'intro' => 'We reviewed your application and cannot approve it at the moment. This is not a judgement of your business, it follows our lending rules.',
                    'retry_hint' => 'You can apply again in :days days. Until then you buy with balance as usual.',
                    'support_hint' => 'Questions? Get in touch.',
                ],

                'downgraded' => [
                    'subject' => 'Pay as you go has ended',
                    'operator_subject' => 'Pay as you go ended: :buyer',
                    'label' => 'Pay as you go',
                    'heading' => 'Pay as you go has ended',
                    'operator_hint' => 'Copy for your information. This concerns :buyer.',
                    'intro' => 'We have ended your pay-as-you-go access. From now on you buy with balance that you top up in advance.',
                    'reason_label' => 'Reason',
                    'open_amount_label' => 'Open amount',
                    'fee_label' => 'Fee',
                    'reasons' => [
                        'settlement_failed' => 'The collection failed twice',
                        'sepa_return' => 'The direct debit was returned',
                        'chargeback' => 'The card payment was disputed',
                        'no_payment_method' => 'No payment method was on file at collection time',
                        'payment_method_revoked' => 'Your payment method was revoked',
                    ],
                    'blocked_hint' => 'While the open amount remains, you cannot buy new leads. Top up your balance by at least the open amount and your account is unlocked right away.',
                    'open_hint' => 'Please settle the open amount with a top-up. The leads you bought remain yours either way.',
                    'deadline_hint' => 'Please settle the open amount within :days days.',
                    'cta' => 'Top up balance',
                    'outro' => 'You can apply for pay as you go again later. Get in touch any time and we will find a solution.',
                ],

            ],

        ],

        'buyer' => [

            'title' => 'Balance & transactions',
            'nav_label' => 'Balance',
            'top_up_heading' => 'Top up balance',

            'balance' => [
                'available_label' => 'Available',
                'reserved_hint' => 'of which reserved: :amount',
                'reserved_tooltip' => 'Reserved amounts are only charged once reachability is confirmed.',
            ],

            'history' => [
                'heading' => 'Transaction history',
                'date' => 'Date',
                'description' => 'Description',
                'amount' => 'Amount',
                'balance_after' => 'Balance after',
                'empty' => 'There are no entries for this period.',
                'lead_link' => 'To lead #:lead',
                'reserved_note' => 'A reservation does not change the balance: it blocks the amount until reachability is settled. Only the charge reduces the balance.',
                'result_count' => '{0} No entries|{1} 1 entry|[2,*] :count entries',

                'filter' => [
                    'type' => 'Type',
                    'period' => 'Period',
                    'all_types' => 'All types',
                    'all_time' => 'All time',
                    'days' => 'Last :days days',
                ],
            ],

        ],

        /*
        |----------------------------------------------------------------------
        | Admin tools for the wallet (LP-WALLET-013)
        |----------------------------------------------------------------------
        */

        'admin' => [

            /*
            |------------------------------------------------------------------
            | Admin tooling for pay as you go (LP-POSTPAID-012)
            |------------------------------------------------------------------
            */
            'postpaid' => [

                'yes' => 'Yes',
                'no' => 'No',
                'blocked_yes' => 'Blocked',
                'blocked_no' => 'Open',
                'no_payment_method' => 'No payment method',
                'reenable_note' => 'Re-enabled by the operator after a downgrade.',

                'payment_mode' => [
                    'prepaid' => 'Prepaid',
                    'postpaid' => 'Pay as you go',
                ],

                'fields' => [
                    'payment_mode' => 'Payment mode',
                    'credit_limit' => 'Credit limit',
                    'credit_limit_cents' => 'Credit limit in cents',
                    'open_amount' => 'Open amount',
                    'open_total' => 'Total receivables',
                    'blocked' => 'Blocked',
                    'disabled_reason' => 'Downgrade reason',
                    'downgrade_reason' => 'Reason',
                    'fee_cents' => 'Fee in cents',
                ],

                'hints' => [
                    'credit_limit_cents' => 'Amount the balance may go negative by. 30000 is 300.00 €.',
                    'downgrade_reason' => 'The reason drives the fee, the purchase block and the wording of the email to the buyer.',
                    'fee_cents' => 'Prefilled with the fee of the selected reason. 0 charges none.',
                ],

                'actions' => [
                    'change_credit_limit' => 'Change credit limit',
                    'change_credit_limit_description' => 'The limit is a permission, not a balance – nothing is posted. Lowering it stops further purchases; the open amount remains.',
                    'credit_limit_changed' => 'Credit limit changed',
                    'settle_now' => 'Collect now',
                    'settle_now_description' => 'Collects today\'s open amount without waiting for the weekly run. SEPA is announced first and only charged after the notice period.',
                    'settled' => 'Collection started',
                    'downgrade' => 'Downgrade to prepaid',
                    'downgrade_description' => 'Ends pay as you go: credit limit to 0, open collections are closed, an open amount leads to a purchase block. Buyer and operator are emailed.',
                    'downgraded' => 'Downgraded to prepaid',
                    'reenable' => 'Re-enable pay as you go',
                    'reenable_description' => 'Re-enables pay as you go despite the earlier downgrade. The eligibility check is bypassed – this is a decision against the rule. The buyer receives the approval email.',
                    'reenabled' => 'Pay as you go re-enabled',
                ],

                'stats' => [
                    'open_receivables' => 'Open receivables',
                    'open_receivables_hint' => '{0} No buyer in the negative|{1} :count buyer in the negative|[2,*] :count buyers in the negative',
                    'in_flight' => 'Collections in progress',
                    'in_flight_hint' => '{0} No collection in flight|{1} :count collection in flight|[2,*] :count collections in flight',
                    'failed' => 'Failed collections',
                    'failed_hint' => 'Failed or returned within the last :days days',
                    'surcharge' => 'Surcharge revenue',
                    'surcharge_hint' => 'Pay-as-you-go surcharge in the current month',
                ],

                'application' => [

                    'resource' => [
                        'label' => 'Pay as you go application',
                        'plural_label' => 'Pay as you go applications',
                    ],

                    'empty_heading' => 'No pending application',
                    'empty_description' => 'As soon as a buyer applies for pay as you go, the application shows up here.',
                    'snapshot_hint' => 'The figures at the time of the application. They are not recalculated – they document what is being decided on.',

                    'sections' => [
                        'application' => 'Application',
                        'eligibility' => 'Eligibility check',
                        'context' => 'Payment method and purchase history',
                    ],

                    'fields' => [
                        'requested_at' => 'Requested',
                        'buyer' => 'Buyer',
                        'workspace' => 'Workspace',
                        'status' => 'Status',
                        'decided_at' => 'Decided',
                        'decided_by' => 'Decided by',
                        'note' => 'Note',
                        'rule' => 'Item',
                        'value' => 'Value',
                        'payment_method' => 'Payment method',
                        'captured_purchases' => 'Captured purchases',
                        'purchase_history' => 'Purchase history (live)',
                    ],

                    'hints' => [
                        'note_required' => 'Stays internal. The buyer only learns when they may apply again.',
                    ],

                    'status' => [
                        'requested' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ],

                    'actions' => [
                        'approve' => 'Approve',
                        'approve_confirm' => 'The buyer then purchases against a credit limit and pays afterwards. They are notified by email.',
                        'approved' => 'Pay as you go approved',
                        'reject' => 'Reject',
                        'reject_confirm' => 'The buyer only learns that we cannot enable it, and when they may apply again.',
                        'rejected' => 'Application rejected',
                    ],

                    'snapshot' => [
                        'eligible' => 'Eligibility',
                        'eligible_yes' => 'All rules met',
                        'eligible_no' => 'Not all rules met',
                        'of_required' => ':value (required: :required)',
                        'captured_purchases' => 'Captured purchases',
                        'account_age_days' => 'Account age in days',
                        'failed_settlements' => 'Failed collections in the observation window',
                        'chargebacks' => 'Returns in the observation window',
                        'clean_history_days' => 'Observation window in days',
                        'balance_cents' => 'Balance',
                        'open_amount_cents' => 'Open amount',
                        'purchase_blocked' => 'Purchase block',
                        'postpaid_disabled_reason' => 'Earlier downgrade',
                        'reasons' => 'Open items',
                    ],

                    'history' => [
                        'captured' => 'Captured purchases',
                        'released' => 'Released purchases',
                        'refunded' => 'Refunded purchases',
                        'revenue' => 'Revenue of the last 90 days',
                    ],

                ],

                'settlement' => [

                    'resource' => [
                        'label' => 'Collection',
                        'plural_label' => 'Collections',
                    ],

                    'empty_heading' => 'No collection yet',
                    'empty_description' => 'Collections are created on the weekly run, at the collection threshold, or by hand on the wallet.',

                    'fields' => [
                        'created_at' => 'Created',
                        'buyer' => 'Buyer',
                        'amount' => 'Amount',
                        'status' => 'Status',
                        'attempts' => 'Attempts',
                        'next_attempt_at' => 'Next attempt',
                        'payment_intent' => 'Payment at the provider',
                        'failure_reason' => 'Failure reason',
                        'invoice_reference' => 'Invoice',
                    ],

                    'status' => [
                        'pending' => 'Pending',
                        'processing' => 'In flight',
                        'retry_pending' => 'Second attempt',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'returned' => 'Returned',
                    ],

                    'trigger' => [
                        'scheduled' => 'Weekly run',
                        'threshold' => 'Collection threshold',
                        'manual' => 'By hand',
                    ],

                    'actions' => [
                        'retry' => 'Retry',
                        'retry_confirm' => 'Charges the stored payment method again. What is collected is today\'s open amount, not the amount of the failed attempt. SEPA is announced again beforehand.',
                        'retried' => 'Collection restarted',
                    ],

                ],

            ],

            'resource' => [
                'label' => 'Wallet',
                'plural_label' => 'Wallets',
                'read_only' => 'Balances cannot be edited here. Every change needs a ledger entry as its record – use the manual adjustment for that.',
            ],

            'platform_owner' => 'Platform',

            'owner_type' => [
                'buyer' => 'Buyer',
                'seller' => 'Seller',
                'platform' => 'Platform',
            ],

            'type' => [
                'topup' => 'Top-up',
                'reserve' => 'Reservation',
                'capture' => 'Capture',
                'release' => 'Release',
                'refund' => 'Refund',
                'earning' => 'Earning',
                'commission' => 'Commission',
                'payout' => 'Payout',
                'adjustment' => 'Adjustment',
                'opening_balance' => 'Opening balance',
                'settlement' => 'Collection',
                'surcharge' => 'Surcharge',
                'fee' => 'Fee',
            ],

            'fields' => [
                'id' => 'No.',
                'owner' => 'Owner',
                'owner_type' => 'Type',
                'balance' => 'Balance',
                'reserved' => 'Reserved',
                'available' => 'Available',
                'currency' => 'Currency',
                'sum' => 'Total',
                'created_at' => 'Time',
                'type' => 'Kind',
                'amount' => 'Amount',
                'description' => 'Description',
                'balance_after' => 'Balance after',
                'reserved_after' => 'Reserved after',
                'idempotency_key' => 'Idempotency key',
                'reference' => 'Reference',
                'created_by' => 'Posted by',
                'meta' => 'Details',
                'amount_cents' => 'Amount in cents',
                'reason' => 'Reason',
                'allow_negative' => 'Allow negative balance',
            ],

            'hints' => [
                'amount_cents' => 'Positive credits, negative debits. 1500 is 15.00 €.',
                'reason' => 'Stays in the ledger permanently and is the only record of why this entry exists.',
                'allow_negative' => 'Only enable if the balance is meant to go negative. Otherwise an excessive debit fails – usually a typo.',
            ],

            'actions' => [
                'adjust' => 'Manual adjustment',
                'adjust_description' => 'Posts a manual adjustment to the ledger. Existing entries stay untouched; corrections are made by counter-entry.',
                'adjust_submit' => 'Post adjustment',
                'adjusted' => 'Adjustment posted',
            ],

            'descriptions' => [
                'adjustment' => 'Manual adjustment: :reason',
            ],

            'ledger' => [
                'heading' => 'Ledger',
                'description' => 'Every entry of this wallet, newest first. Expanding a row shows reference, idempotency key and details.',
                'empty_heading' => 'No entries yet',
            ],

            'stats' => [
                'platform_balance' => 'Platform wallet',
                'platform_balance_hint' => 'Accumulated commission less refunds and adjustments',
                'commission_total' => 'Commission total',
                'commission_total_hint' => 'Sum of all commission entries, reversals included',
                'open_payouts' => 'Open payouts',
                'open_payouts_hint' => '{0} No open request|{1} :count request awaiting transfer|[2,*] :count requests awaiting transfer',
            ],

            'payout' => [

                'resource' => [
                    'label' => 'Payout',
                    'plural_label' => 'Payouts',
                ],

                'read_only' => 'The amount left the seller wallet when the request was made. Rejecting it books the amount back.',
                'empty_heading' => 'No open payout',
                'empty_description' => 'As soon as a seller requests a payout, it shows up here.',
                'iban_changed' => 'Careful: the stored IBAN changed after the request, which was made against •••• :last4.',

                'fields' => [
                    'requested_at' => 'Requested',
                    'seller' => 'Seller',
                    'amount' => 'Amount',
                    'iban' => 'IBAN',
                    'status' => 'Status',
                    'processed_at' => 'Decided',
                    'processed_by' => 'Decided by',
                    'note' => 'Note',
                ],

                'hints' => [
                    'note_optional' => 'Optional, for instance the transfer reference.',
                    'note_required' => 'The seller receives the rejection by email; the reason belongs with it.',
                ],

                'actions' => [
                    'mark_paid' => 'Transferred',
                    'mark_paid_confirm' => 'Only confirm once the transfer has actually been made. Nothing is posted – the money left the wallet with the request.',
                    'marked_paid' => 'Marked as transferred',
                    'reject' => 'Reject',
                    'reject_confirm' => 'The amount is booked back to the seller wallet as an adjustment.',
                    'rejected' => 'Payout rejected',
                ],

            ],

            'purchase' => [

                'resource' => [
                    'label' => 'Lead purchase',
                    'plural_label' => 'Lead purchases',
                ],

                'read_only' => 'Purchase records are not edited. The money side only changes through a refund.',
                'empty_heading' => 'No lead purchase yet',

                'status' => [
                    'reserved' => 'Reserved',
                    'captured' => 'Captured',
                    'released' => 'Released',
                    'refunded' => 'Refunded',
                ],

                'fields' => [
                    'purchased_at' => 'Purchased',
                    'lead' => 'Lead',
                    'buyer' => 'Buyer',
                    'seller' => 'Seller',
                    'status' => 'Status',
                    'price' => 'Price',
                    'commission' => 'Commission',
                    'seller_net' => 'Seller earning',
                    'captured_at' => 'Captured',
                    'released_at' => 'Released',
                    'refunded_at' => 'Refunded',
                    'reason' => 'Reason',
                ],

                'hints' => [
                    'reason' => 'Stays permanently in every ledger entry of this refund.',
                ],

                'actions' => [
                    'refund' => 'Refund lead',
                    'refund_confirm' => 'The price goes back to the buyer, earning and commission are reversed – even if that pushes the seller wallet negative. If there is an open complaint for this purchase, decide it there instead.',
                    'refunded' => 'Lead purchase refunded',
                ],

            ],

        ],

        'payment_methods' => [
            'title' => 'Payment methods',
            'description' => 'Pay as you go needs a way to collect payment. You can add a SEPA direct debit mandate or a card. You enter the details directly with our payment provider; we only see the last four digits.',
            'choose' => 'Choose a payment method',
            'add' => 'Add payment method',
            'added' => 'Payment method added.',
            'remove' => 'Remove',
            'removed' => 'Payment method removed.',
            'default_badge' => 'Used for collection',
            'empty' => 'No payment method added yet.',

            'types' => [
                'sepa_debit' => 'SEPA direct debit',
                'card' => 'Card',
            ],

            'type_hints' => [
                'sepa_debit' => 'Collected from your bank account. You can have a debit returned for up to eight weeks.',
                'card' => 'Credit or debit card. The payment is decided immediately.',
            ],

            'status' => [
                'active' => 'Active',
                'revoked' => 'Revoked',
                'failed' => 'Failed',
            ],

            'mandate' => [
                'heading' => 'SEPA direct debit mandate',
                'creditor_label' => 'Creditor',
                'creditor_id_label' => 'Creditor identifier',
                'text' => 'By signing this mandate form, you authorise :creditor (creditor identifier :creditor_id) to send instructions to your bank to debit your account and your bank to debit your account in accordance with the instructions from :creditor. You are entitled to a refund from your bank under the terms and conditions of your agreement with your bank. A refund must be claimed within eight weeks starting from the date on which your account was debited.',
                'accept' => 'I grant the SEPA direct debit mandate above.',
                'prenotification_hint' => 'We notify you of the amount and the debit date by email before every collection.',
                'accepted_at' => 'Mandate granted on :date',
            ],

            'errors' => [
                'mandate_required' => 'Without a granted SEPA direct debit mandate we cannot collect anything. Please confirm the mandate.',
                'mandate_missing' => 'The payment provider did not report a direct debit mandate. Please try again.',
                'setup_not_completed' => 'The payment method was not confirmed (status: :status). Please try again.',
                'setup_intent_unknown' => 'We do not know this process. Please start again.',
                'last_method_postpaid' => 'This is your only payment method and you buy with pay as you go. Add another one first, then you can remove this one.',
                'last_method_open_amount' => 'This is your only payment method and :amount is still open. Add another one first or settle the open amount.',
                'provider_unavailable' => 'The payment provider cannot be reached right now. Please try again in a few minutes.',
            ],
        ],

        'top_up' => [
            'title' => 'Top up balance',
            'nav_label' => 'Balance',
            'balance_label' => 'Your balance',
            'reserved_label' => 'Of which reserved',
            'available_label' => 'Available',
            'description' => 'Your balance is used to buy leads on the marketplace. Pick an amount, payment runs through the usual checkout. The amount is credited as soon as the payment is confirmed.',
            'amount_label' => 'Amount',
            'amount_hint' => 'At least :min €, at most :max €. Whole euros only.',
            'submit' => 'Continue to payment',
            'checkout_hint' => 'Your balance lets you buy leads in the marketplace. It does not expire.',
            'payment_hint' => 'Payment is handled by the portal payment provider. You will find the invoice and receipt under "Orders".',

            'errors' => [
                'min' => 'The smallest top-up amount is :amount €.',
                'max' => 'The largest top-up amount is :amount €.',
                'unavailable' => 'Topping up is not possible at the moment. Please contact support.',
            ],

            'success' => [
                'amount_label' => 'Top-up',
                'done_text' => ':amount have been credited and are available right away.',
                'balance_now' => 'Your balance now',
                'covers' => '{0} not enough for a lead|{1} enough for 1 lead|[2,*] enough for :count leads',
                'invoice' => 'Invoice as PDF',
                'invoice_mail' => 'The invoice also goes to :email.',
                'pending' => [
                    'heading' => 'One moment',
                    'text' => 'Your payment arrived. We are crediting the balance right now.',
                    'step_paid' => 'Payment confirmed',
                    'step_credit' => 'Balance is being credited',
                    'step_invoice' => 'Invoice is being created',
                    'hint' => 'This usually takes less than five seconds. You can leave the page open.',
                ],
                'slow' => [
                    'heading' => 'Taking a little longer',
                    'text' => 'The payment is confirmed, the credit is stuck for a moment. Nothing was charged twice - we credit the balance automatically once it goes through and send you an email.',
                    'reference' => 'Payment reference',
                    'support' => 'Write to support',
                ],
                'heading' => 'Balance topped up',
                'pending_heading' => 'Almost there',
                'text' => ':amount has been added to your balance.',
                'pending_text' => 'As soon as the payment is confirmed we will credit the amount. For bank transfers this usually takes one to two working days.',
                'balance_label' => 'Your balance',
                'pending_addition' => '+:amount once the payment arrives',
                'unknown_heading' => 'We do not know this order',
                'unknown_text' => 'The link may have expired. You will find your balance on the marketplace.',
            ],

            'mail' => [
                'subject' => 'Balance topped up: :amount',
                'label' => 'Balance',
                'heading' => 'Balance topped up',
                'intro' => 'Your payment has arrived. We have added :amount to your balance.',
                'amount_label' => 'Topped up',
                'balance_label' => 'New balance',
                'history_hint' => 'You can review every entry of your balance in the transaction history in the portal at any time.',
                'receipt_hint' => 'The invoice and payment receipt for this top-up are in the order confirmation we sent you as well.',
                'cta' => 'To the transaction history',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Order confirmation as payment receipt (LP-WALLET-019)
        |----------------------------------------------------------------------
        |
        | This mail is the receipt for the payment: order number, line items,
        | discount and total. The credit itself is confirmed by `top_up.mail`
        | with amount and new balance -- the receipt therefore points at it
        | instead of repeating the balance.
        */

        'order_mail' => [
            'subject' => 'Your order at :app',
            'label' => 'Payment receipt',
            'heading' => 'Order confirmed',
            'intro' => 'Thank you for your order. The payment arrived.',
            'intro_topup' => 'Thank you for your top-up. The payment arrived and the amount was credited to your wallet.',
            'order_number' => 'Order number',
            'date' => 'Ordered on',
            'items_heading' => 'Line items',
            'quantity' => 'Quantity: :count',
            'topup_amount' => 'Credited to your wallet: :amount',
            'subtotal' => 'Subtotal',
            'discount' => 'Discount',
            'total' => 'Total',
            'cta' => 'To the marketplace',
            'topup_hint' => 'Your new wallet balance is stated in the top-up confirmation we just sent you.',
            'support' => 'Questions about your order? Write to us at :email.',
            'outro' => 'Kind regards, your :app team',
        ],

        /*
        |----------------------------------------------------------------------
        | Ledger entry types (LP-WALLET-012)
        |----------------------------------------------------------------------
        */

        'types' => [
            'topup' => 'Top-up',
            'reserve' => 'Reserved',
            'capture' => 'Charged',
            'release' => 'Released',
            'refund' => 'Refund',
            'earning' => 'Earning',
            'commission' => 'Commission',
            'payout' => 'Payout',
            'adjustment' => 'Adjustment',
            'opening_balance' => 'Opening balance',
            'settlement' => 'Collection',
            'surcharge' => 'Surcharge',
            'fee' => 'Fee',
        ],

        /*
        |----------------------------------------------------------------------
        | Seller portal: price, earnings, payout (LP-WALLET-012)
        |----------------------------------------------------------------------
        */

        'seller' => [

            'title' => 'Earnings',
            'nav_label' => 'Earnings',

            'price' => [
                'heading' => 'Price per lead',
                'description' => 'This is the price your leads are offered at in the marketplace.',
                'label' => 'Price per lead (gross, paid by the buyer)',
                'preview' => 'You receive :net per lead (commission :percent %).',
                'change_hint' => 'Price changes apply to new purchases. Leads already sold keep their price.',
                'range' => 'Allowed range: :min to :max.',
                'out_of_range' => 'The price must be between :min and :max.',
                'saved' => 'Price saved.',
                'submit' => 'Save price',
            ],

            'balance' => [
                'available' => 'Payout balance',
                'available_hint' => 'Settled earnings you can have paid out.',
                'pending' => 'Pending settlement',
                'pending_badge' => 'pending',
                'pending_hint' => 'Your share of leads that have not been settled yet. Not a balance: if a purchase is released, the amount is gone.',
                'earnings' => 'Earnings, last :days days',
                'earnings_hint' => 'Sum of credited earnings, less reversed leads.',
            ],

            'history' => [
                'heading' => 'Transaction history',
                'empty' => 'No entries yet.',
                'date' => 'Date',
                'type' => 'Type',
                'description' => 'Description',
                'amount' => 'Amount',
                'balance_after' => 'Balance after',
                'show_more' => 'Show more',
            ],

            'payout' => [
                'heading' => 'Request a payout',
                'description' => 'From :minimum you can have your balance paid out. The amount is debited immediately and transferred within the next business days.',
                'iban_label' => 'IBAN',
                'iban_missing' => 'None on file',
                'iban_cta' => 'Add your IBAN in the workspace settings',
                'amount_label' => 'Amount',
                'amount_hint' => 'Available: :available, minimum :minimum.',
                'below_minimum' => 'A payout is possible from :minimum. Currently available: :available.',
                'confirm' => 'Request the payout now? The amount is debited from your balance immediately.',
                'submit' => 'Request payout',
                'invalid_amount' => 'Please enter a valid amount.',
                'requested' => 'Payout of :amount requested.',
                'history_heading' => 'Previous payouts',
                'history_empty' => 'No payout requested yet.',
                'requested_at' => 'Requested',
                'amount' => 'Amount',
                'status' => 'Status',
                'note' => 'Note',
            ],

        ],

        'errors' => [
            'not_updatable' => 'A wallet entry cannot be changed once it has been created.',
            'not_deletable' => 'A wallet entry cannot be deleted.',
            'insufficient_reserve' => 'Your available balance is not sufficient. Missing: :missing.',
            'insufficient_balance' => 'The balance is not sufficient for this entry: :balance available, :required required.',
            'release_exceeds_reserved' => 'Only :reserved is reserved, but :requested should be released.',
            'purchase_blocked' => 'Your account is blocked until the outstanding amount has been settled.',
        ],

        'descriptions' => [
            'topup' => 'Top-up via order :order',
            'reserve' => 'Reservation for lead #:lead',
            'release' => 'Reservation for lead #:lead released',
            'release_for_capture' => 'Reservation for lead #:lead released for capture',
            'capture' => 'Charge for lead #:lead',
            'earning' => 'Earning from lead #:lead',
            'commission' => 'Commission from lead #:lead',
            'refund' => 'Refund for lead #:lead',
            'earning_reversal' => 'Reversal of the earning from lead #:lead',
            'commission_reversal' => 'Reversal of the commission from lead #:lead',
            'surcharge' => 'Pay as you go surcharge from lead #:lead',
            'surcharge_reversal' => 'Reversal of the surcharge from lead #:lead',
            'chargeback' => 'Reversal of the top-up from order :order',
            'payout' => 'Payout #:payout',
            'payout_rejected' => 'Reversal of the rejected payout #:payout',
            'settlement' => 'Collection of the open amount (settlement #:settlement)',
            'settlement_return' => 'Return of the payment for settlement #:settlement',
            'fee' => [
                'settlement_failed' => 'Dunning fee after a failed collection',
                'sepa_return' => 'Direct debit return fee',
                'chargeback' => 'Fee for a disputed card payment',
                'no_payment_method' => 'Fee: no payment method on file',
                'payment_method_revoked' => 'Fee: payment method revoked',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Seller payouts (LP-WALLET-010)
        |----------------------------------------------------------------------
        */

        'payout' => [

            'status' => [
                'requested' => 'Requested',
                'paid' => 'Paid out',
                'rejected' => 'Rejected',
            ],

            'profile' => [
                'heading' => 'Bank account',
                'description' => 'We pay out your earnings from sold leads to this account.',
                'iban' => 'IBAN',
                'iban_helper' => 'No IBAN on file yet. We cannot pay out without one.',
                'iban_stored' => 'On file: •••• :last4. Enter a new IBAN to change it, otherwise leave empty.',
                'invalid_iban' => 'That is not a valid IBAN.',
            ],

            'errors' => [
                'missing_iban' => 'We need your IBAN before we can pay out. Please add it to your profile.',
                'below_minimum' => 'Payouts start at :minimum; you requested :amount.',
                'exceeds_balance' => 'That much is not available: :available can be paid out, you requested :amount.',
            ],

            'mail' => [

                'amount_label' => 'Amount',
                'iban_label' => 'IBAN',
                'seller_label' => 'Seller',
                'reference_label' => 'Reference',
                'note_label' => 'Reason',

                'requested' => [
                    'subject' => 'Payout requested: :amount by :seller',
                    'label' => 'Payout',
                    'heading' => 'New payout request',
                    'intro' => ':seller requested a payout of :amount. The amount has already been debited from their balance.',
                    'hint' => 'Transfer the amount and then mark the request as paid in the admin area. Rejecting it credits the balance back automatically.',
                ],

                'processed' => [

                    'label' => 'Payout',

                    'paid' => [
                        'subject' => 'Your payout of :amount is on its way',
                        'heading' => 'Payout transferred',
                        'intro' => 'We have transferred your payout of :amount.',
                        'hint' => 'Depending on your bank the credit takes one to two business days.',
                    ],

                    'rejected' => [
                        'subject' => 'Your payout of :amount was rejected',
                        'heading' => 'Payout rejected',
                        'intro' => 'We could not process your payout of :amount. The amount is back in your balance.',
                        'hint' => 'You can request the payout again at any time once the cause is resolved.',
                    ],

                ],

            ],

        ],

        'opening_balance' => [
            'description' => 'Opening balance carried over from the previous credit system (:credits credits).',
        ],

        'settlement' => [

            'mail' => [
                'subject' => 'Lead purchase not settled: lead #:lead',
                'label' => 'Lead settlement',
                'heading' => 'A resolved lead could not be settled',
                'intro' => 'The contact status of lead #:lead is ":status", but the money side of the purchase remained open after several attempts.',
                'reason' => 'Error',
                'outro' => 'Please check the purchase record: a reservation nobody releases keeps blocking the buyer\'s balance.',
            ],

        ],

        'verify' => [

            'mail' => [
                'subject' => 'Wallet check: :count discrepancy/discrepancies against the ledger',
                'label' => 'Wallet check',
                'heading' => 'Balances differ from the ledger',
                'intro' => 'The daily consistency check found wallets whose cached balance does not match the sum of their entries.',
                'checked' => ':count wallet(s) checked.',
                'wallet' => 'Wallet #:id',
                'balance' => 'Balance',
                'reserved' => 'Reserved',
                'expected' => 'Expected',
                'actual' => 'Actual',
                'findings' => 'Violated postpaid rules',
                'repaired' => 'The cached balance was reset to the ledger during this run.',
                'reservation_totals' => 'The reserved amounts of the buyer wallets do not match the open lead purchases: expected :expected, actual :actual.',
                'outro' => 'The ledger is always authoritative. Please find the cause before correcting the balance.',
            ],

        ],

    ],

];
