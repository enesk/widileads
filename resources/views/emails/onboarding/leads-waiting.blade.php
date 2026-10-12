{{--
    Tag 3 der Onboarding-Strecke (LP-ONBOARD-014): bis zu drei Vorschaukarten
    aus den Kaufkriterien des Kaeufers.

    Die Karten sind anonymisiert -- kein Name, keine E-Mail, keine Rufnummer.
    Entschieden hat das nicht diese Ansicht, sondern der LeadContactResolver
    ueber LeadPresenter::forTeamView(); hier steht nur, was der
    OnboardingMailContent herausgibt. Wer hier eine Kontaktzeile ergaenzt,
    verschenkt Ware: Eine Mail laesst sich weiterleiten.
--}}
@php
    $tint = config('app.email_color_tint');
    $remaining = max(0, $total - count($cards));
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ trans_choice('mail.onboarding.leads_waiting.heading', $total, ['count' => $total]) }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('mail.onboarding.leads_waiting.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ trans_choice('mail.onboarding.leads_waiting.heading', $total, ['count' => $total]) }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 24px; line-height: 24px">
                            {{ __('mail.onboarding.leads_waiting.intro', ['name' => $recipient->name]) }}
                        </p>

                        @foreach ($cards as $card)
                            <table style="width: 100%; margin-bottom: 12px; border-collapse: separate; border-spacing: 0; border: 1px solid #e2e8f0; border-radius: 10px" cellpadding="0" cellspacing="0" role="none" bgcolor="#f8fafc">
                                <tr>
                                    <td class="sm-px-6" style="padding: 16px 20px">
                                        <p style="margin: 0 0 2px; font-size: 15px; font-weight: 600; color: #0f172a">
                                            {{ $card['funnel'] }}
                                        </p>
                                        <p style="margin: 0 0 10px; font-size: 13px; color: #94a3b8">
                                            {{ __('mail.onboarding.leads_waiting.card.region') }} {{ $card['region'] }}
                                            @if ($card['age'] !== '')
                                                &middot; {{ $card['age'] }}
                                            @endif
                                        </p>

                                        @if ($card['chips'] !== [])
                                            <p style="margin: 0 0 10px; font-size: 14px; line-height: 21px; color: #475569">
                                                {{ implode(' · ', $card['chips']) }}
                                            </p>
                                        @endif

                                        <p style="margin: 0; font-size: 14px; color: #0f172a">
                                            <span style="color: #94a3b8">{{ __('mail.onboarding.leads_waiting.card.price') }}:</span>
                                            <strong>{{ $card['price'] }}</strong>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        @endforeach

                        @if ($remaining > 0)
                            <p style="margin: 12px 0 0; font-size: 14px; color: #64748b">
                                {{ __('mail.onboarding.leads_waiting.more', ['count' => $remaining]) }}
                            </p>
                        @endif

                        <p style="margin: 12px 0 0; font-size: 13px; line-height: 20px; color: #94a3b8">
                            {{ __('mail.onboarding.leads_waiting.note') }}
                        </p>

                        <table style="width: 100%; margin-top: 32px" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td align="center">
                                    <a href="{{ $marketplaceUrl }}" style="display: inline-block; border-radius: 10px; background-color: {{ $tint }}; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none">
                                        {{ __('mail.onboarding.leads_waiting.cta') }}
                                    </a>
                                </td>
                            </tr>
                        </table>

                        @include('emails.onboarding.partials.footer')
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</x-layouts.email>
