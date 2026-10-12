{{ trans_choice('mail.onboarding.leads_waiting.heading', $total, ['count' => $total]) }}

{{ __('mail.onboarding.leads_waiting.intro', ['name' => $recipient->name]) }}
@foreach ($cards as $card)

- {{ $card['funnel'] }} ({{ __('mail.onboarding.leads_waiting.card.region') }} {{ $card['region'] }})
@if ($card['chips'] !== [])
  {{ implode(' · ', $card['chips']) }}
@endif
  {{ __('mail.onboarding.leads_waiting.card.price') }}: {{ $card['price'] }}
@endforeach
@if ($total > count($cards))

{{ __('mail.onboarding.leads_waiting.more', ['count' => $total - count($cards)]) }}
@endif

{{ __('mail.onboarding.leads_waiting.note') }}

{{ __('mail.onboarding.leads_waiting.cta') }}: {{ $marketplaceUrl }}

@include('emails.onboarding.partials.text-footer')
