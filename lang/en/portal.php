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

    'placeholder' => [
        'text' => 'This page is still being built. Until it is ready, you will find the feature in the existing area.',
        'link' => 'Back to the existing area',
    ],

    'close' => 'Close',
    'skip_to_content' => 'Skip to content',
    'open_menu' => 'Open menu',
    'account' => 'Account',
    'balance' => 'Balance',
    'top_up' => 'Top up',

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
        'marketplace' => 'Marketplace',
        'buying_criteria' => 'Buying criteria',
        'caller_id' => 'Caller ID',
        'balance' => 'Balance',
        'orders' => 'Orders',
        'payments' => 'Payments',
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
];
