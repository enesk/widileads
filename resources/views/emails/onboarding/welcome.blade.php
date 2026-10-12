{{--
    Tag 0 der Onboarding-Strecke (LP-ONBOARD-014).

    Aufbau wie die uebrigen Portalmails: getoenter Kopf, weisse Karte,
    Tabellen und Inline-Styles -- Outlook kennt weder Flexbox noch Grid, und
    externe Stylesheets werden von den meisten Postfaechern entfernt.

    Die Illustration ist ein PNG-Export des Motivs "success" aus
    LP-ONBOARD-005 und liegt in doppelter Aufloesung vor: Das Bild kommt in
    480 Pixeln und steht auf 240 -- so bleibt es auf Retina-Schirmen scharf.
    Inline-SVG koennen viele Postfaecher nicht, deshalb der Export.

    Genau ein Knopf, und zwar auf den naechsten offenen Schritt. Ist nichts
    mehr offen, fuehrt er in den Marktplatz.
--}}
@php
    $tint = config('app.email_color_tint');
    $url = $action['url'] ?? $marketplaceUrl;
    $label = $action['label'] ?? __('mail.onboarding.welcome.cta_marketplace');
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('mail.onboarding.welcome.heading') }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('mail.onboarding.welcome.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('mail.onboarding.welcome.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <table style="width: 100%; margin: 0 0 24px" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td align="center">
                                    <img src="{{ asset('images/mail/illustrations/success@2x.png') }}"
                                         alt="{{ __('portal.illustrations.success') }}"
                                         width="240" height="180"
                                         style="width: 240px; max-width: 100%; height: auto; border: 0">
                                </td>
                            </tr>
                        </table>

                        <p style="margin: 0 0 28px; line-height: 24px">
                            {{ __('mail.onboarding.welcome.intro', ['name' => $recipient->name]) }}
                        </p>

                        @if ($action !== null)
                            <h2 style="margin: 0 0 12px; font-size: 13px; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase; color: #64748b">
                                {{ __('mail.onboarding.welcome.next_heading') }}
                            </h2>
                        @else
                            <p style="margin: 0 0 12px; line-height: 24px">
                                {{ __('mail.onboarding.welcome.done') }}
                            </p>
                        @endif

                        <table style="width: 100%; margin-top: 16px" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td align="center">
                                    <a href="{{ $url }}" style="display: inline-block; border-radius: 10px; background-color: {{ $tint }}; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none">
                                        {{ $label }}
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin: 16px 0 0; font-size: 13px; line-height: 20px; color: #94a3b8">
                            {{ __('mail.fallback') }}<br>
                            <a href="{{ $url }}" style="color: {{ $tint }}; word-break: break-all">{{ $url }}</a>
                        </p>

                        @include('emails.onboarding.partials.footer')
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</x-layouts.email>
