<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_CALLBACK', '/auth/google/callback'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_CALLBACK', '/auth/github/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_CALLBACK', '/auth/facebook/callback'),
    ],

    'twitter-oauth-2' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect' => env('TWITTER_CALLBACK', '/auth/twitter-oauth-2/callback'),
    ],

    'linkedin-openid' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => env('LINKEDIN_CALLBACK', '/auth/linkedin-openid/callback'),
    ],

    'bitbucket' => [
        'client_id' => env('BITBUCKET_CLIENT_ID'),
        'client_secret' => env('BITBUCKET_CLIENT_SECRET'),
        'redirect' => env('BITBUCKET_CALLBACK', '/auth/bitbucket/callback'),
    ],

    'gitlab' => [
        'client_id' => env('GITLAB_CLIENT_ID'),
        'client_secret' => env('GITLAB_CLIENT_SECRET'),
        'redirect' => env('GITLAB_CALLBACK', '/auth/gitlab/callback'),
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_signing_secret' => env('STRIPE_WEBHOOK_SIGNING_SECRET'),
    ],

    'paddle' => [
        'vendor_id' => env('PADDLE_VENDOR_ID'),
        'client_side_token' => env('PADDLE_CLIENT_SIDE_TOKEN'),
        'vendor_auth_code' => env('PADDLE_VENDOR_AUTH_CODE'),
        'public_key' => env('PADDLE_PUBLIC_KEY'),
        'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
        'is_sandbox' => env('PADDLE_IS_SANDBOX', false),
    ],

    'lemon-squeezy' => [
        'api_key' => env('LEMON_SQUEEZY_API_KEY'),
        'store_id' => env('LEMON_SQUEEZY_STORE_ID'),
        'signing_secret' => env('LEMON_SQUEEZY_SIGNING_SECRET'),
        'is_test_mode' => env('LEMON_SQUEEZY_IS_TEST_MODE', false),
    ],

    'creem' => [
        'api_key' => env('CREEM_API_KEY'),
        'webhook_secret' => env('CREEM_WEBHOOK_SECRET'),
        'is_test_mode' => env('CREEM_IS_TEST_MODE', false),
    ],

    'polar' => [
        'access_token' => env('POLAR_ACCESS_TOKEN'),
        'webhook_secret' => env('POLAR_WEBHOOK_SECRET'),
        'is_sandbox' => env('POLAR_IS_SANDBOX', false),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),

        // Der Verify-Service der Rufnummernbestaetigung (LP-ONBOARD-004). Ein
        // eigener Dienst in der Twilio-Konsole mit eigener SID ("VA..."): Twilio
        // haelt dort die Codes, ihre Laufzeit und die Absenderkennung. Fehlt der
        // Wert, laeuft nur der Betrieb mit config('phone_verification.fake').
        'verify_sid' => env('TWILIO_VERIFY_SID'),

        /*
         | WebOTP (Chrome fuer Android): Der Browser bietet den Code aus der
         | SMS nur an, wenn deren letzte Zeile "@<host> #<code>" lautet und
         | <host> der Host der aufgerufenen Seite ist. Twilio nimmt den
         | Wortlaut aus dem Template des Verify-Services -- wir koennen dort
         | nur Platzhalter fuellen. Steht dieser Schalter auf true, reicht
         | TwilioVerifyClient den Host als Substitution "host" mit; das
         | Template muss ihn als {{host}} fuehren. Default aus, weil Twilio
         | eine Substitution ablehnt, die das Template nicht kennt.
         |
         | Ticket #60: Dieses Template ist bei Twilio ein Custom Template und
         | nur per Support-Antrag zu bekommen (POST /v2/Templates gibt 405),
         | Freigabe 2 bis 4 Wochen -- laeuft als #65. Bis dahin bleibt der
         | Schalter aus.
         */
        'verify_webotp' => (bool) env('TWILIO_VERIFY_WEBOTP', false),

        // Welcher Host in die Schlusszeile gehoert. Leer heisst: der Host aus
        // APP_URL -- beim Test durch einen Tunnel also der Tunnel-Host, denn
        // WebOTP vergleicht mit der Adresse im Browser.
        'verify_webotp_host' => env('TWILIO_VERIFY_WEBOTP_HOST'),
    ],

];
