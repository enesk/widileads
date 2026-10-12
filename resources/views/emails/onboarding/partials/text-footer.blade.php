{{ __('mail.closing', ['app' => config('app.wordmark')]) }}

--
{{ __('mail.onboarding.unsubscribe.note') }}
{{ __('mail.onboarding.unsubscribe.link') }}: {{ $unsubscribeUrl }}
