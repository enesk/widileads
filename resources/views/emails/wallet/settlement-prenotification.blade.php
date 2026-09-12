{{--
    LP-POSTPAID-014: SEPA-Vorabankuendigung vor dem Einzug.

    Pflichtinhalt sind Betrag und Belastungsdatum; dazu stehen hier die letzten
    vier Stellen der IBAN und die Mandatsreferenz, damit der Kaeufer die
    Buchung seinem Mandat zuordnen kann. Aufbau wie die uebrigen Portalmails:
    getoenter Kopf, weisse Karte, Tabellen und Inline-Styles, weil Outlook
    weder Flexbox noch Grid kennt.
--}}
@php
    $tint = config('app.email_color_tint');
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('marketplace.wallet.prenotification.mail.heading') }} — {{ $amount }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('marketplace.wallet.prenotification.mail.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('marketplace.wallet.prenotification.mail.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 28px; line-height: 24px">
                            {{ __('marketplace.wallet.prenotification.mail.intro', ['amount' => $amount, 'date' => $chargeDate]) }}
                        </p>

                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 10px; border: 1px solid #e2e8f0" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; width: 55%">
                                    {{ __('marketplace.wallet.prenotification.mail.amount_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 16px; font-weight: 700; color: #0f172a" align="right">
                                    {{ $amount }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0">
                                    {{ __('marketplace.wallet.prenotification.mail.date_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 16px; font-weight: 600; color: #0f172a; border-top: 1px solid #e2e8f0" align="right">
                                    {{ $chargeDate }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0">
                                    {{ __('marketplace.wallet.prenotification.mail.iban_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 16px; font-weight: 600; color: #0f172a; border-top: 1px solid #e2e8f0" align="right">
                                    {{ $iban }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0">
                                    {{ __('marketplace.wallet.prenotification.mail.mandate_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 14px; font-weight: 600; color: #0f172a; border-top: 1px solid #e2e8f0" align="right">
                                    {{ $mandateReference }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0">
                                    {{ __('marketplace.wallet.prenotification.mail.creditor_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 14px; font-weight: 600; color: #0f172a; border-top: 1px solid #e2e8f0" align="right">
                                    {{ $creditorName }}<br>
                                    <span style="font-weight: 400; color: #64748b">{{ $creditorId }}</span>
                                </td>
                            </tr>
                        </table>

                        <p style="margin: 24px 0 0; line-height: 24px">
                            {{ __('marketplace.wallet.prenotification.mail.coverage_hint') }}
                        </p>

                        <p style="margin: 12px 0 0; font-size: 14px; line-height: 22px; color: #64748b">
                            {{ __('marketplace.wallet.prenotification.mail.support_hint') }}
                        </p>

                    </td>
                </tr>

            </table>
        </td>
    </tr>
</x-layouts.email>
