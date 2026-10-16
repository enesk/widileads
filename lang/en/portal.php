<?php

declare(strict_types=1);

return [
    'label' => 'Portal',

    'pages' => [
        'overview' => 'Overview',
        'marketplace' => 'Marketplace',
        'leads' => 'Purchased leads',
        'lead_detail' => 'Lead detail',
        'wallet' => 'Top up balance',
        'transactions' => 'Transactions',
        'buyer_profile' => 'Buyer profile',
        'buying_criteria' => 'Buying criteria',
        'caller_id' => 'Own phone number',
        'orders' => 'Orders',
        'funnels' => 'Funnels',
        'earnings' => 'Earnings',
        'payout' => 'Payout',
        'reports' => 'Reports',
        'settings' => 'Settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Illustration labels (LP-ONBOARD-006)
    |--------------------------------------------------------------------------
    */

    'illustrations' => [
        'phone' => 'Smartphone with an incoming message',
        'success' => 'Check mark in a circle: done',
        'industries' => 'Three industry tiles, one of them selected',
        'region' => 'Outline of Germany with a location and its radius',
        'payment' => 'Wallet with a card and coins',
        'lead-preview' => 'Lead with a hidden phone number',
        'checklist' => 'Clipboard with three completed steps',
        'call' => 'Phone handset with a stopwatch',
    ],

    /*
    |--------------------------------------------------------------------------
    | Onboarding-Gate: die Rufnummer (LP-ONBOARD-003)
    |--------------------------------------------------------------------------
    */

    'phone_gate' => [
        'text' => 'Please verify your phone number before you see the marketplace. Without it you cannot call a purchased lead, and a lead only counts as handled once you have called.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rufnummernbestaetigung (LP-ONBOARD-004)
    |--------------------------------------------------------------------------
    */

    'phone_verification' => [
        'wait' => [
            'seconds' => '{1} one second|[2,*] :count seconds',
            'minutes' => '{1} one minute|[2,*] :count minutes',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Einrichtungs-Wizard (LP-ONBOARD-007)
    |--------------------------------------------------------------------------
    */

    'onboarding' => [
        /*
        |----------------------------------------------------------------------
        | Phone verification, the three screens (LP-ONBOARD-005)
        |----------------------------------------------------------------------
        |
        | The service's messages are NOT here but with the thing they belong
        | to: `call.caller_id.*` for the number and the limits,
        | `call.caller_id.hint.*` for the failure states of the call. Only the
        | messages unique to this screen live here.
        */
        'phone' => [
            'title' => 'Confirm your phone number',
            'welcome' => 'Welcome to widileads, :name',

            'number' => [
                'heading' => 'Confirm your phone number',
                'text' => 'You will call your leads from this number later on. We call you once and read out a 6-digit code. Mobile or landline, both work.',
                'country' => 'Country code',
                'field' => 'Phone number',
                'placeholder' => '172 1234567',
                'submit' => 'Request the call',
                'sending' => 'Connecting the call ...',
            ],

            'call' => [
                'heading' => 'Your phone is about to ring',
                'text' => 'We are calling :number. Pick up and follow the instructions.',
                'edit' => 'Change number',
                'code_label' => 'Your code',
                'code_hint' => 'Enter it on your phone as soon as the recording asks for it.',
                'waiting' => 'Waiting for the confirmation ...',
                'validity' => 'The code is valid for :minutes minutes.',
                'retry_in' => 'No call? Try again in :countdown',
                'retry' => 'Call me again',
                'retrying' => 'Connecting the call ...',
                'blocked_in' => 'Available again in :countdown',
                'no_call_heading' => 'No call arriving?',
                'no_call_text' => 'Check the number for a typo, and whether your line blocks withheld or unknown callers.',
            ],

            'success' => [
                'heading' => 'Number confirmed',
                'text' => 'Now let us set up in three short steps which requests you want to see.',
                'submit' => "Let's go",
            ],

            'errors' => [
                'required' => 'Please enter your phone number.',
                'no_workspace' => 'There is no workspace on your account yet. Please contact support, we will set it up.',
            ],
        ],

        'wizard' => [
            'title' => ':counter - :step',
            'counter' => 'Step :step of :total',
            'rail_label' => 'Setup',
            'back' => 'Back',
            'next' => 'Next',
            'finish' => 'Continue to payment',
            'working' => 'One moment ...',
            'skip' => 'Set up later',
            'step_pending' => 'This step is still being built. "Next" skips it for now.',
            'state' => [
                'done' => 'completed',
                'active' => 'current step',
                'open' => 'open',
            ],
        ],

        'steps' => [
            'phone' => [
                'label' => 'Phone number',
            ],
            'industries' => [
                'label' => 'Industries',
                'heading' => 'Which requests do you want to buy?',
                'text' => 'Pick one or more industries. You can change this any time.',
            ],
            'region' => [
                'label' => 'Region',
                'heading' => 'Where should the requests come from?',
                'text' => 'Nationwide shows you the most. If you only work in your area, narrow it down here.',
            ],
            'payment' => [
                'label' => 'Payment',
                'heading' => 'How do you want to pay?',
                'text' => 'Prepaid balance or pay as you go at the end of the month. You only ever pay once you buy a lead.',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | The example lead card (x-app.lead-card variant="demo")
        |----------------------------------------------------------------------
        |
        | The sentence about when the phone number is released is NOT here: it
        | comes from the LeadPresenter (call.phone_release.*) so the example and
        | the real thing read the same.
        */
        'lead_card' => [
            'example' => 'Example',
        ],

        'industries' => [
            'weekly' => 'approx. :count requests / week',
            'fresh' => 'new',
            'show_all' => 'Show all industries',
            'summary' => 'With this selection you will see approx. {count} new requests per week.',
            'error' => 'Pick at least one industry so we can show you requests.',

            'empty' => [
                'title' => 'No industries on offer yet',
                'text' => 'No portal is live right now that we could show you requests from. Carry on -- as soon as one is added, you pick it in your buying criteria.',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Step 2: region (LP-ONBOARD-009)
        |----------------------------------------------------------------------
        */
        'region' => [
            'recommended' => 'Recommended',

            'nationwide' => [
                'title' => 'All of Germany',
                'text' => 'The most requests, nothing wasted',
            ],

            'radius' => [
                'title' => 'Only in my region',
                'text' => 'Set a postcode and a radius',
            ],

            'plz' => [
                'label' => 'Postcode',
                'placeholder' => '76133',
            ],

            'radius_field' => [
                'label' => 'Radius',
                'unit' => 'km',
            ],

            'summary' => 'This area recently saw approx. :count requests per week in your industries.',
            'empty' => 'Few requests arrive here so far. A wider radius is worth it.',

            'errors' => [
                'unknown_plz' => 'We do not know this postcode. Check the five digits.',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Step 3: payment (LP-ONBOARD-010)
        |----------------------------------------------------------------------
        */
        'payment' => [
            'amount' => '€:amount',
            'popular' => 'Popular',
            'covers' => '{0} not enough for a lead|{1} covers about one lead|[2,*] covers about :count leads',
            'more_leads' => '+:count',
            'later' => 'Later, let me look around first',
            'continue' => 'Back to setup',
            'error' => 'Pick a package or pay as you go so you can buy leads.',

            'payg' => [
                'title' => 'Pay as you go: only pay for what you buy',
                'text' => 'No balance up front. Your purchases are collected and charged later.',
                'hint' => 'You add a payment method for this. Each purchase carries a :percent % surcharge.',
            ],

            'billing' => [
                'note' => 'You only pay for leads where the call is answered.',
                'link' => 'How is this counted?',
                'heading' => 'How we bill',
                'close' => 'Close',
                'understood' => 'Got it',
            ],
        ],

        /*
         | The final screen (LP-ONBOARD-011). See the German file for why the
         | product name is spelled out instead of using config('app.wordmark').
         */
        'done' => [
            'title' => 'Almost done',
            'heading' => 'Almost done - this is how a lead works',
            'text' => 'One look at an example request, then you are in the marketplace.',

            'demo' => [
                'caption' => 'Not a real request',
                'name' => 'Max Beispiel',
                'funnel' => 'Electrician',
                'chips' => [
                    'Replace fuse box',
                    'Single-family house',
                    'Within 2 weeks',
                ],
                'phone_hint' => 'Fully visible once you buy.',
            ],

            'markers' => [
                'close' => 'Close hint',
                'chips' => [
                    'label' => 'What do these details mean?',
                    'hint' => 'The details come from the form the prospect filled in. They tell you before buying whether the request fits you.',
                ],
                'phone' => [
                    'label' => 'Why is the number hidden?',
                    'hint' => 'You get the full phone number when you buy. Before that no buyer sees it -- not even you.',
                ],
                'price' => [
                    'label' => 'When is the price charged?',
                    'hint' => 'The price is reserved when you buy and only charged once you actually had the prospect on the phone.',
                ],
            ],

            'explainer' => [
                'heading' => 'How billing works',
                'counter' => 'Step :step of :total',
                'balance' => 'Balance',
                'balance_postpaid' => 'Open amount',
                'next' => 'Next',
                'restart' => 'Start over',
                'go_to' => 'Go to step :step',
                'release_note' => 'The full phone number is released once a call has been answered or :attempts calls are on record. If the lead is credited back, it stays hidden.',
                'steps' => [
                    'ringing' => [
                        'title' => 'You call through widileads',
                        'text' => 'You start the call in the portal: your phone rings first, then we connect you. The person you are calling sees your verified number – that is why you verify it beforehand.',
                    ],
                    'answered' => [
                        'title' => 'The call is answered',
                        'text' => 'From :seconds seconds of conversation the lead is charged: :amount comes off your balance.',
                        'text_postpaid' => 'From :seconds seconds of conversation the lead is charged: :amount plus a :percent % surcharge, :total in total, goes onto your next invoice.',
                    ],
                    'missed' => [
                        'title' => 'No answer?',
                        'text' => 'After :attempts attempts across :days days with at least :hours hours in between the lead is free – you get the amount back. If you let the deadline pass without those attempts, it is charged.',
                        'text_postpaid' => 'After :attempts attempts across :days days with at least :hours hours in between the lead is free – it does not go onto your invoice. If you let the deadline pass without those attempts, it is charged.',
                    ],
                ],
            ],

            'deadline' => 'You have :days days. Fewer than :attempts attempts before the deadline counts as reached.',
            'to_marketplace' => 'To the marketplace',
            'top_up_first' => 'Top up balance first',
        ],

        /*
        |----------------------------------------------------------------------
        | The activation card on the dashboard (LP-ONBOARD-012)
        |----------------------------------------------------------------------
        */
        'activation' => [
            'heading' => 'Your first steps',
            'progress' => ':done of :total',
            'hide' => 'Hide',

            'steps' => [
                'phone' => 'Phone number confirmed',
                'criteria' => 'Industries and region chosen',
                'criteria_todo' => 'Choose industries and region',
                'topup' => 'Balance topped up',
                'purchase' => 'First lead bought',
                'call' => 'First call made',
            ],

            'actions' => [
                'criteria' => 'Choose industries',
                'topup' => 'Top up balance',
                'purchase' => 'To the marketplace',
                'call' => 'Call now',
            ],

            'activated' => [
                'heading' => "You're ready to go.",
                'text' => 'First call done -- from here on only the people you reach count. You will not see this card again.',
            ],
        ],
    ],

    'placeholder' => [
        'text' => 'This page is still being built. Until it is ready, you will find the feature in the existing area.',
        'link' => 'Back to the existing area',
    ],

    'close' => 'Close',
    'announcement' => [
        'region' => 'Announcement',
        'dismiss' => 'Dismiss announcement',
    ],
    'skip_to_content' => 'Skip to content',
    'open_menu' => 'Open menu',
    'account' => 'Account',
    'balance' => 'Balance',
    'top_up' => 'Top up',

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */
    'profile' => [
        'heading' => 'Profile',
        'description' => 'Your name, your sign-in details and the security of your account.',
        'save' => 'Save',

        'account' => [
            'heading' => 'Account',
            'name' => 'Name',
            'email' => 'Email address',
            'current_password' => 'Current password',
            'password_required' => 'We need your current password to change the email address.',
            'password_wrong' => 'That password is not correct.',
            'email_warning' => 'The new address has to be confirmed. We send a link there; until you follow it you cannot use the portal.',
            'email_changed' => 'Address changed. Confirm it using the link in your inbox.',
            'unverified' => 'Your address is not confirmed yet.',
            'resend' => 'Send link again',
            'verification_sent' => 'Confirmation link sent.',
            'verification_throttled' => 'The link was just sent. Please wait a moment.',
        ],

        'password' => [
            'heading' => 'Change password',
            'description' => 'You stay signed in after changing it.',
            'new' => 'New password',
            'confirm' => 'Repeat new password',
            'hint' => 'At least 8 characters.',
            'mismatch' => 'The two passwords do not match.',
            'submit' => 'Change password',
            'saved' => 'Password changed.',
        ],

        'two_factor' => [
            'heading' => 'Two-factor authentication',
            'active' => 'Active. We ask for an additional code when you sign in.',
            'inactive' => 'Not set up. A second factor protects your account if your password ever leaks.',
            'manage' => 'Manage',
            'setup' => 'Set up',
        ],

        'side' => [
            'status' => 'Address',
            'verified' => 'Confirmed',
            'unverified' => 'Pending',
            'member_since' => 'Member since',
            'workspace' => 'Workspace',
            'phone' => 'Phone number',
            'address_hint' => 'The billing address belongs to the workspace and lives under',
        ],
    ],

    'menu' => [
        'profile' => 'Profile',
        'settings' => 'Settings',
        'logout' => 'Log out',
    ],

    'workspace' => [
        'buyer' => 'Buyer workspace',
        'seller' => 'Seller workspace',
        'switch' => 'Switch workspace',
    ],

    'nav' => [
        'label' => 'Main navigation',
        'dashboard' => 'Dashboard',
        'my_leads' => 'My leads',
        'team_leads' => 'Team leads',
        'marketplace' => 'Marketplace',
        'buying_criteria' => 'Buying criteria',
        'caller_id' => 'Caller ID',
        'balance' => 'Balance',
        'orders' => 'Orders',
        'payments' => 'Payments',
        'transactions' => 'Transactions',
        'soon' => 'This page is still being built.',
        'group' => [
            'marketplace' => 'Marketplace',
            'billing' => 'Billing',
        ],
    ],

    'filters' => [
        'label' => 'Filters',
        'sort' => 'Sorting',
    ],

    'toast' => [
        'dismiss' => 'Dismiss message',
        'saved' => 'Saved.',
        'discarded' => 'Changes discarded.',
        'note_saved' => 'Note saved.',
        'status_saved' => 'Status saved.',
    ],
    'pagination' => [
        'label' => 'Pages',
        'previous' => 'Previous',
        'next' => 'Next',
        'page' => 'Page :page',
    ],

    'empty' => [
        'title' => 'Nothing found',
        'description' => 'There is nothing to show here right now.',
    ],
    'topup' => [
        'back' => 'Back to the marketplace',
        'back_onboarding' => 'Back to setup',
        'heading' => 'Top up balance',
        'description' => 'You buy leads in the marketplace with your balance. It does not expire.',
        'balance_label' => 'Current balance',
        'form_heading' => 'Top-up',
        'form_hint' => 'A lead costs :price. You are only charged once you reach the enquirer.',
        'packages_legend' => 'How much would you like to top up?',
        'package_leads' => '{0} not enough for a lead|{1} enough for one lead|[2,*] enough for :count leads',
        'popular' => 'Popular',
        'custom_toggle' => 'Enter a different amount',
        'custom_label' => 'Amount in euros',
        'custom_placeholder' => 'e.g. :amount',
        'custom_hint' => 'At least :min €, at most :max €. Whole euros only.',
        'workspace_label' => 'For which company?',
        'subtotal' => 'Subtotal',
        'vat' => 'incl. :percent % VAT',
        'total' => 'Total',
        'vat_note' => 'All prices include VAT.',
        'after' => 'Balance after top-up',
        'submit' => 'Pay :amount now',
        'legal' => 'By purchasing you agree to the :terms and the :privacy.',
        'legal_terms' => 'terms of service',
        'legal_privacy' => 'privacy policy',
        'payment_hint' => 'Payment is processed securely through the portal payment provider.',
        'rules' => [
            'heading' => 'How we bill',
            'items' => [
                'On purchase the lead price is only reserved.',
                'Once you reach the enquirer (:seconds seconds of conversation or more), it is charged.',
                'After :attempts attempts across :days days without a conversation the amount is released.',
                'Balance does not expire, the invoice arrives by email right away.',
            ],
            'link' => 'All billing rules',
        ],
        'recent' => [
            'heading' => 'Recent top-ups',
            'link' => 'All entries',
        ],
    ],

    'dashboard' => [

        'greeting' => [
            'morning' => 'Good morning, :name',
            'day' => 'Hello, :name',
            'evening' => 'Good evening, :name',
        ],

        'summary' => '{0} No call is due today, :leads new leads match your criteria.|{1} 1 call is open, :leads new leads match your criteria.|[2,*] :calls calls are open, :leads new leads match your criteria.',

        'top_up' => 'Top up credit',
        'to_marketplace' => 'To the marketplace',

        'kpi' => [
            'balance' => 'Available balance',
            'reserved' => ':amount reserved',
            'new_leads' => 'New matching leads',
            'new_leads_meta' => 'since yesterday · :count in total',
            'open_calls' => 'Open calls',
            'due_today' => '{0} no deadline ends today|{1} 1 deadline ends today|[2,*] :count deadlines end today',
            'reached' => 'Reached (30 days)',
            'reached_value' => ':captured of :decided',
            'reached_meta' => ':rate % · :released not billed',
        ],

        'deadlines' => [
            'heading' => 'Deadline running out',
            'all' => 'All open',
            'empty' => 'No lead is waiting for a call.',
            'today' => 'today, :time',
            'in_days' => '{1} tomorrow|[2,*] in :count days',
            'over' => 'Deadline passed',
            'none' => 'no deadline',
            'meta' => ':region · :done of :total attempts',
            'call' => 'Call now',
            'rule' => 'Without three attempts before the deadline the lead is billed. If you reach nobody, the amount is released.',
        ],

        'suggestions' => [
            'heading' => 'New leads for you',
            'all' => 'All on the marketplace',
            'empty' => 'No lead matches your criteria right now.',
            'badge' => 'New',
            'view' => 'View',
        ],

        'stats' => [
            'heading' => 'Last 30 days',
            'range' => ':from – :to',
            'bought' => 'Bought',
            'bought_value' => '{0} no leads|{1} 1 lead|[2,*] :count leads',
            'captured' => 'Billed',
            'captured_meta' => '{0} no lead|{1} 1 lead|[2,*] :count leads',
            'released' => 'Released',
            'released_meta' => '{0} no lead unreached|{1} 1 lead unreached|[2,*] :count leads unreached',
            'rate' => 'Reachability rate',
            'average' => 'Average across all buyers: :rate %',
        ],

        'criteria' => [
            'heading' => 'Your buying criteria',
            'action' => 'Adjust criteria',
        ],

        'activity' => [
            'heading' => 'Recent activity',
            'all' => 'Full history',
            'empty' => 'Nothing has happened yet.',
            'call' => 'Call to a lead',
            'topup' => 'Balance topped up – :amount',
            'reserve' => 'Lead bought – :amount reserved',
            'capture' => 'Lead reached – :amount billed',
            'release' => 'Lead not reached – :amount released',
            'refund' => 'Refund – :amount',
            'earning' => 'Earning – :amount',
            'commission' => 'Commission – :amount',
            'payout' => 'Payout – :amount',
            'adjustment' => 'Adjustment – :amount',
            'opening_balance' => 'Opening balance – :amount',
        ],

        'caller_id' => [
            'heading' => 'Phone number',
            'default' => 'Default for calls through :app',
            'action' => 'Confirm another number',
            'missing' => 'No confirmed number yet. Without one you cannot call leads.',
            'verify' => 'Confirm phone number',
        ],

        'when' => [
            'today' => 'today, :time',
            'yesterday' => 'yesterday, :time',
            'on' => ':date, :time',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pay as you go in the portal (LP-POSTPAID-010)
    |--------------------------------------------------------------------------
    */

    'postpaid' => [

        'label' => 'Pay as you go',

        'apply' => [
            'heading' => 'Pay as you go',
            'description' => 'Buy leads without paying upfront and settle afterwards. We collect your outstanding amount automatically.',
            'points' => [
                'You buy against a credit limit instead of your balance.',
                'Your limit starts at :limit.',
                'We collect every :weekday, or earlier once :threshold is outstanding.',
                'Every lead price carries a surcharge of :percent %.',
            ],
            'checklist_heading' => 'What we check first',
            'rule_met' => 'Met',
            'rule_open' => 'Open',
            'rules' => [
                'purchases' => 'At least :required settled lead purchases – you have :count.',
                'account_age' => 'Account at least :required days old – yours is :days days old.',
                'history' => 'No payment incident in the last :days days.',
                'not_blocked' => 'No purchase block on your account.',
                'payment_method' => 'A payment method is on file.',
            ],
            'submit' => 'Apply for pay as you go',
            'payment_method_cta' => 'Add a payment method',
            'payment_method_hint' => 'Without a payment method on file we cannot collect anything.',
            'not_eligible_hint' => 'You can apply as soon as every point is met.',
            'pending_heading' => 'Application submitted',
            'pending_text' => 'We received your application on :date and will get back to you by email.',
            'rejected_heading' => 'Rejected',
            'rejected_text' => 'Your last application was rejected.',
            'rejected_retry' => 'You can apply again in :days days.',
            'rejected_retry_now' => 'You can apply again now.',
            'submitted' => 'We received your application and will get back to you by email.',
        ],

        'balance' => [
            'open_label' => 'Outstanding amount',
            'remaining_label' => 'Still available',
            'remaining_value' => ':available of :limit',
            'tooltip' => 'The outstanding amount is collected every :weekday, or once :threshold is reached.',
            'exhausted' => 'Your credit limit is used up. The outstanding amount is settled with the next collection.',
            'surcharge_hint' => 'incl. :percent % pay-as-you-go surcharge',
            'settlements_link' => 'View collections',
        ],

        'blocked' => [
            'heading' => 'Your account is blocked.',
            'text' => 'Outstanding amount: :amount',
            'action' => 'Settle now',
        ],

        'payment_methods' => [
            'heading' => 'Payment methods',
            'description' => 'We collect your outstanding amount through this method. You enter IBAN or card number directly with the payment provider.',
            'empty_title' => 'No payment method yet',
            'empty_description' => 'Add a payment method so we can collect your outstanding amount.',
            'add' => 'Add payment method',
            'cancel' => 'Cancel',
            'remove' => 'Remove',
            'remove_confirm' => 'Really remove this payment method?',
            'removed' => 'Payment method removed.',
            'added' => 'Payment method saved.',
            'make_default' => 'Make default',
            'default_set' => 'Default changed.',
            'default_badge' => 'Default',
            'mandate_since' => 'Mandate since :date',
            'steps' => [
                'type' => 'Choose type',
                'details' => 'Enter details',
                'mandate' => 'Confirm mandate',
                'done' => 'Done',
            ],
            'step_of' => 'Step :step of :total',
            'type_heading' => 'How would you like to pay?',
            'details_heading' => 'Your payment details',
            'details_hint' => 'The form runs directly with the payment provider. We only store the last four digits.',
            'mandate_heading' => 'SEPA direct debit mandate',
            'done_heading' => 'Payment method saved',
            'done_text' => 'We will collect your outstanding amount through :method from now on.',
            'back' => 'Back',
            'next' => 'Next',
            'save' => 'Save payment method',
            'close' => 'Done',
            'unavailable' => 'The payment form could not be loaded. Please try again later.',
        ],

        'settlements' => [
            'heading' => 'Collections',
            'description' => 'Every collection of your outstanding amount with date, amount and payment method.',
            'empty_title' => 'No collection yet',
            'empty_description' => 'As soon as we collect your outstanding amount, it shows up here – with a receipt to download.',
            'date' => 'Date',
            'amount' => 'Amount',
            'method' => 'Payment method',
            'method_unknown' => 'No longer on file',
            'status' => 'Status',
            'invoice' => 'Receipt',
            'status_labels' => [
                'pending' => 'Pending',
                'processing' => 'In progress',
                'retry_pending' => 'Retry on :date',
                'retry_pending_short' => 'Retry scheduled',
                'paid' => 'Paid',
                'failed' => 'Failed',
                'returned' => 'Charged back',
            ],
        ],
    ],
];
