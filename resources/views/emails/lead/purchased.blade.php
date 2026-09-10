{{--
    FB-054: Kaufbestaetigung. Kontaktdaten kommen ausschliesslich aus dem
    Presenter -- er liefert sie im Klartext, weil der Kaufbeleg existiert.
    Diese Ansicht entscheidet nichts, sie gibt aus.

    Ohne Rufnummer (FB-085): Sie wird erst nach der Abrechnung freigegeben und
    steht dann im Portal. Wer diese Zeile wieder einbaut, haengt die Freigabe an
    einer Mail auf, die man weiterleiten kann.

    Aufbau wie die uebrigen Portalmails: getoenter Kopf, weisse Karte, Tabellen
    und Inline-Styles -- Outlook kennt weder Flexbox noch Grid, und externe
    Stylesheets werden von den meisten Postfaechern entfernt.
--}}
@php
    $tint = config('app.email_color_tint');
    $funnelName = $lead->funnel?->name ?? __('marketplace.listing.unknown_funnel');

    $contactRows = [
        __('funnel.buyer.fields.contact_name') => $presenter->name(),
        __('marketplace.listing.email') => $presenter->email(),
        __('marketplace.listing.region') => $presenter->postalCode(),
    ];
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('marketplace.purchase.mail.heading') }} — {{ $funnelName }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                {{-- Kopf: worum es geht, aus welchem Fragebogen. --}}
                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('marketplace.purchase.mail.funnel_label') }}: {{ $funnelName }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('marketplace.purchase.mail.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 28px; line-height: 24px">
                            {{ __('marketplace.purchase.mail.intro', ['funnel' => $funnelName]) }}
                        </p>

                        {{-- Kontakt: das Wichtigste, deshalb hervorgehoben. --}}
                        <h2 style="margin: 0 0 12px; font-size: 13px; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase; color: #64748b">
                            {{ __('marketplace.purchase.mail.contact_heading') }}
                        </h2>

                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #e2e8f0; border-radius: 10px" cellpadding="0" cellspacing="0" role="none" bgcolor="#f8fafc">
                            @foreach ($contactRows as $label => $value)
                                <tr>
                                    <td style="padding: 12px 20px; border-bottom: {{ $loop->last ? 'none' : '1px solid #e2e8f0' }}; font-size: 13px; color: #64748b; width: 38%; vertical-align: top">
                                        {{ $label }}
                                    </td>
                                    <td style="padding: 12px 20px 12px 0; border-bottom: {{ $loop->last ? 'none' : '1px solid #e2e8f0' }}; font-size: 16px; font-weight: 600; color: #0f172a; vertical-align: top">
                                        @if ($value === null || $value === '')
                                            <span style="font-weight: 400; color: #94a3b8">{{ __('leads.contact.missing') }}</span>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>

                        <p style="margin: 12px 0 0; font-size: 13px; line-height: 20px; color: #94a3b8">
                            {{ __('marketplace.purchase.mail.phone_note') }}
                        </p>

                        @if ($leadUrl !== null)
                            <table style="width: 100%; margin-top: 32px" cellpadding="0" cellspacing="0" role="none">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $leadUrl }}" style="display: inline-block; border-radius: 10px; background-color: {{ $tint }}; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none">
                                            {{ __('marketplace.purchase.mail.cta') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0">&zwj;</div>

                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #64748b">
                            {{ __('marketplace.purchase.mail.outro') }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</x-layouts.email>
