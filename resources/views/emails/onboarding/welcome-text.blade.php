{{ __('mail.onboarding.welcome.heading') }}

{{ __('mail.onboarding.welcome.intro', ['name' => $recipient->name]) }}
@if ($action !== null)

{{ __('mail.onboarding.welcome.next_heading') }}: {{ $action['label'] }}
{{ $action['url'] }}
@else

{{ __('mail.onboarding.welcome.done') }}
{{ $marketplaceUrl }}
@endif

@include('emails.onboarding.partials.text-footer')
