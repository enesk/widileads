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

];
