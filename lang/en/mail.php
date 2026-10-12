<?php

/*
|--------------------------------------------------------------------------
| System mail copy
|--------------------------------------------------------------------------
|
| Account, invitation and subscription -- everything that has no domain
| language file of its own. Addressed as __('mail.<area>.<key>').
|
*/

return [

    'support' => 'Questions? Write to us at :email.',
    'closing' => 'Kind regards, your :app team',
    'fallback' => 'If the button does not work, copy this address into your browser:',

    'verify_email' => [
        'subject' => 'Confirm your email address',
        'label' => 'Account',
        'heading' => 'Confirm your email address',
        'intro' => 'Welcome to :app. Confirm your email address and your account is ready.',
        'cta' => 'Confirm email address',
    ],

    'reset_password' => [
        'subject' => 'Set a new password',
        'label' => 'Account',
        'heading' => 'Reset your password',
        'intro' => 'A new password was requested for your account. Use the button to set it.',
        'cta' => 'Reset password',
        'expiry' => 'The link is valid for 60 minutes.',
        'ignore' => 'If you did not request this, there is nothing to do.',
    ],

    'otp' => [
        'label' => 'Sign-in',
        'heading' => 'Your sign-in code',
        'intro' => 'Use this code to sign in on :url.',
        'warning' => 'Do not share this code with anyone. If you did not request it, ignore this email.',
    ],

    'invitation' => [
        'subject' => 'Invitation to :tenant',
        'label' => 'Invitation',
        'heading' => 'You have been invited to :tenant',
        'intro' => 'You have been invited to work in the workspace ":tenant". Use the button to accept.',
        'cta' => 'Accept invitation',
    ],

    'subscribed' => [
        'subject' => 'Welcome to :app',
        'label' => 'Subscription',
        'heading' => 'Welcome to :app',
        'intro' => 'Your subscription to the ":plan" plan is active. Glad to have you on board.',
        'feedback' => 'If something is missing or off, write to us. Feedback decides what we work on next.',
    ],

    'subscription_cancelled' => [
        'subject' => 'Your subscription has been cancelled',
        'label' => 'Subscription',
        'heading' => 'Sorry to see you go',
        'intro' => 'Your subscription has been cancelled. Tell us what we could do better.',
        'return' => 'You can subscribe again at any time, straight from your account.',
        'thanks' => 'Thank you for being with us.',
    ],

    'payment_failed' => [
        'subject' => 'Your payment failed',
        'label' => 'Subscription',
        'heading' => 'Payment failed',
        'greeting' => 'Hello :name,',
        'intro' => 'We could not process the payment for your ":plan" subscription. Add another payment method, otherwise access ends.',
        'cta' => 'Update payment method',
    ],

    'expiring_soon' => [
        'subject' => 'Your subscription expires soon',
        'label' => 'Subscription',
        'heading' => 'Your subscription expires soon',
        'greeting' => 'Hello :name,',
        'intro' => 'Your subscription to the ":plan" plan expires shortly. Complete it to keep your access.',
        'cta' => 'Complete subscription',
    ],

    'referral_reward' => [
        'subject' => 'Your referral paid off',
        'label' => 'Referral',
        'heading' => 'You earned a reward',
        'intro' => ':name joined through your referral. Here is your coupon code.',
        'code_heading' => 'Your coupon code',
        'more' => 'Keep referring, every accepted referral earns another reward.',
        'cta' => 'To your account',
    ],
    /*
    |--------------------------------------------------------------------------
    | Onboarding sequence (LP-ONBOARD-014)
    |--------------------------------------------------------------------------
    */

    'onboarding' => [

        'unsubscribe' => [
            'note' => 'This email is part of the setup guidance.',
            'link' => 'Stop receiving setup tips',
            'heading' => 'Unsubscribed',
            'text' => 'You will not receive setup tips any more. Order confirmations, invoices and notices about your leads keep coming — they belong to your account.',
            'back' => 'To the homepage',
        ],

        'welcome' => [
            'subject' => 'You are ready to go',
            'label' => 'Setup',
            'heading' => 'You are ready to go',
            'intro' => 'Hi :name, your account is set up. From now on you see the requests that match your criteria — and you only pay once you buy one.',
            'next_heading' => 'Your next step',
            'done' => 'You are all set. Have a look at what is on the marketplace right now.',
            'cta_marketplace' => 'To the marketplace',
        ],

        'billing_explained' => [
            'subject' => 'How billing works at widileads',
            'label' => 'Billing',
            'heading' => 'You only pay for the people you reach',
            'intro' => 'Hi :name, here are the three cases in which a lead costs money — and in which it does not.',
            'states' => [
                'ringing' => [
                    'title' => 'You call through widileads',
                    'text' => 'You start the call from the portal. Your own number stays hidden.',
                ],
                'answered' => [
                    'title' => 'Call answered: the lead is charged',
                    'text' => 'From :seconds seconds of conversation the lead counts as reached and the price is charged.',
                ],
                'missed' => [
                    'title' => 'Not reached: the lead is free',
                    'text' => 'After :attempts attempts across :days days with at least :hours hours in between you get the amount back.',
                ],
            ],
            'cta' => 'To the marketplace',
        ],

        'leads_waiting' => [
            'subject' => 'Requests are waiting for you',
            'label' => 'Marketplace',
            'heading' => '{1} One request matches you|[2,*] :count requests match you',
            'intro' => 'Hi :name, this is on the marketplace right now and matches your industries and your region.',
            'card' => [
                'region' => 'Region',
                'price' => 'Price',
            ],
            'more' => 'And :count more.',
            'note' => 'You see the name and phone number after the purchase.',
            'cta' => 'View requests',
        ],

        'nudge' => [
            'subject' => 'One step is still missing',
            'label' => 'Setup',
            'heading' => ':done of :total steps done',
            'intro' => 'Hi :name, you are almost there. One step is missing until you talk to your first lead.',
            'next_heading' => 'Your next step',
            'cta_marketplace' => 'To the marketplace',
        ],

    ],

];
