{{ __('mail.onboarding.billing_explained.heading') }}

{{ __('mail.onboarding.billing_explained.intro', ['name' => $recipient->name]) }}

1. {{ __('mail.onboarding.billing_explained.states.ringing.title') }}
   {{ __('mail.onboarding.billing_explained.states.ringing.text') }}

2. {{ __('mail.onboarding.billing_explained.states.answered.title', ['seconds' => $seconds]) }}
   {{ __('mail.onboarding.billing_explained.states.answered.text', ['seconds' => $seconds]) }}

3. {{ __('mail.onboarding.billing_explained.states.missed.title', ['attempts' => $attempts]) }}
   {{ __('mail.onboarding.billing_explained.states.missed.text', ['attempts' => $attempts, 'days' => $days, 'hours' => $hours]) }}

{{ __('mail.onboarding.billing_explained.cta') }}: {{ $marketplaceUrl }}

@include('emails.onboarding.partials.text-footer')
