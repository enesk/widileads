<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reports (FB-E6)
    |--------------------------------------------------------------------------
    */

    'funnel' => [
        'heading' => 'Funnel drop-off',
        'nav_label' => 'Reports',
        'funnel' => 'Funnel',
        'from' => 'From',
        'until' => 'Until',
        'views' => 'Views',
        'submissions' => 'Submissions',
        'abandoned' => 'Abandoned',
        'completion_rate' => 'Completion rate',
        'steps' => 'Steps',
        'step' => 'Step',
        'step_views' => 'Viewed',
        'step_completions' => 'Completed',
        'drop_offs' => 'Dropped off',
        'drop_off_rate' => 'Drop-off rate',
        'no_funnel' => 'There is no funnel in this workspace yet.',
        'no_steps' => 'This version has no steps - or the funnel has not been published yet.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Onboarding funnel (LP-ONBOARD-015)
    |--------------------------------------------------------------------------
    */

    'onboarding' => [
        'heading' => 'Onboarding',
        'nav_label' => 'Onboarding',
        'description' => 'Where new buyers get stuck - from sign-up to the first call. Every number comes from our own database and is cached for ten minutes.',
        'funnel_heading' => 'Funnel',
        'funnel_hint' => 'Per stage the absolute number and the share of the previous stage. A workspace counts for a stage once it reached it, regardless of the stages before - back-filled steps can therefore push a share above 100%.',
        'period' => 'Period',
        'last_days' => 'Last :days days',
        'export' => 'Export CSV',
        'empty' => 'No buyer signed up in this period.',
        'of_previous' => 'Share of the previous stage',
        'activation_rate' => 'Activation within 7 days',
        'activation_rate_hint' => ':activated of :registered sign-ups bought their first lead within :days days.',
        'activation_window_open' => '{1} One sign-up still has an open window.|[2,*] :count sign-ups still have an open window.',
        'median' => 'Median time to first purchase',
        'median_hint' => '{0} No first purchase in this period yet.|{1} Based on one buyer.|[2,*] Based on :count buyers.',
        'no_data' => 'No data',
        'hours' => '{1} :count hour|[2,*] :count hours',
        'days' => '{1} :count day|[2,*] :count days',
        'stuck_heading' => 'Currently stuck',
        'stuck_hint' => 'Buyer workspaces that have been on the same stage for more than :hours hours - deliberately without a period filter, because the old cases are the point.',
        'stuck_empty' => 'No workspace is stuck beyond the threshold.',
        'workspace' => 'Workspace',
        'stage' => 'On stage',
        'next_stage' => 'Waiting for',
        'waiting' => 'Since',
        'contact' => 'Contact',
        'no_contact' => 'No contact on file',

        'stages' => [
            'registered' => 'Signed up',
            'email_verified' => 'Email confirmed',
            'phone_verified' => 'Phone verified',
            'industries_selected' => 'Industries',
            'region_selected' => 'Region',
            'payment_setup' => 'Payment',
            'first_topup' => 'First top-up',
            'first_purchase' => 'First purchase',
            'first_call' => 'First call',
        ],

        'csv' => [
            'stage' => 'Stage',
            'count' => 'Count',
            'percent_of_previous' => 'Share of previous stage (%)',
            'percent_of_total' => 'Share of sign-ups (%)',
        ],
    ],

];
