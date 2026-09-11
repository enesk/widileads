{{--
    LP-WALLET-009: Bestaetigung einer Aufladung.

    Beide Betraege kommen aus der Buchung und werden hier nur ausgegeben --
    diese Ansicht rechnet nichts und fragt nichts ab. Aufbau wie die uebrigen
    Portalmails: getoenter Kopf, weisse Karte, Tabellen und Inline-Styles.
    Outlook kennt weder Flexbox noch Grid, und externe Stylesheets werden von
    den meisten Postfaechern entfernt.
--}}
@php
    $tint = config('app.email_color_tint');
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('marketplace.wallet.top_up.mail.heading') }} — {{ $amount }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('marketplace.wallet.top_up.mail.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('marketplace.wallet.top_up.mail.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 28px; line-height: 24px">
                            {{ __('marketplace.wallet.top_up.mail.intro', ['amount' => $amount]) }}
                        </p>

                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 10px; border: 1px solid #e2e8f0" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; width: 60%">
                                    {{ __('marketplace.wallet.top_up.mail.amount_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 16px; font-weight: 600; color: #0f172a" align="right">
                                    {{ $amount }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0">
                                    {{ __('marketplace.wallet.top_up.mail.balance_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 16px; font-weight: 700; color: #0f172a; border-top: 1px solid #e2e8f0" align="right">
                                    {{ $balance }}
                                </td>
                            </tr>
                        </table>

                        <p style="margin: 24px 0 0; line-height: 24px">
                            {{ __('marketplace.wallet.top_up.mail.history_hint') }}
                        </p>

                        {{--
                            Verweis statt Wiederholung (LP-WALLET-019): Rechnung,
                            Bestellnummer und Rabatt stehen in der
                            Bestellbestaetigung; diese Mail belegt die Gutschrift.
                        --}}
                        <p style="margin: 12px 0 0; font-size: 14px; line-height: 22px; color: #64748b">
                            {{ __('marketplace.wallet.top_up.mail.receipt_hint') }}
                        </p>

                        <table style="width: 100%; margin-top: 32px" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td align="center">
                                    <a href="{{ $historyUrl }}" style="display: inline-block; border-radius: 10px; background-color: {{ $tint }}; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none">
                                        {{ __('marketplace.wallet.top_up.mail.cta') }}
                                    </a>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

            </table>
        </td>
    </tr>
</x-layouts.email>
