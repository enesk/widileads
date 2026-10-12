{{ __('mail.onboarding.nudge.heading', ['done' => $done, 'total' => $total]) }}

{{ __('mail.onboarding.nudge.intro', ['name' => $recipient->name]) }}
@if ($action !== null)

{{ __('mail.onboarding.nudge.next_heading') }}: {{ $action['label'] }}
{{ $action['url'] }}
@else

{{ __('mail.onboarding.nudge.cta_marketplace') }}: {{ $marketplaceUrl }}
@endif

@include('emails.onboarding.partials.text-footer')
