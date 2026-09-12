{{--
    LP-POSTPAID-008: Interne Meldung ueber einen technisch gescheiterten
    Einzug. Empfaenger ist die Support-Adresse, nicht der Kaeufer.

    Aufbau wie die uebrigen internen Meldungen (emails/wallet/ledger-mismatch):
    Tabellen und Inline-Styles, weil Outlook weder Flexbox noch Grid kennt.
--}}
@php
    $tint = config('app.email_color_tint');
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('marketplace.wallet.settlement_notice.error.heading') }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('marketplace.wallet.settlement_notice.error.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 28px; line-height: 24px">
                            {{ __('marketplace.wallet.settlement_notice.error.intro') }}
                        </p>

                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 10px; border: 1px solid #e2e8f0" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; width: 40%">
                                    {{ __('marketplace.wallet.settlement_notice.error.settlement_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 15px; font-weight: 600; color: #0f172a" align="right">
                                    #{{ $settlementId }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0">
                                    {{ __('marketplace.wallet.settlement_notice.error.wallet_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 15px; font-weight: 600; color: #0f172a; border-top: 1px solid #e2e8f0" align="right">
                                    #{{ $walletId }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0">
                                    {{ __('marketplace.wallet.settlement_notice.amount_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 15px; font-weight: 700; color: #0f172a; border-top: 1px solid #e2e8f0" align="right">
                                    {{ $amount }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0">
                                    {{ __('marketplace.wallet.settlement_notice.error.reason_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 13px; color: #0f172a; border-top: 1px solid #e2e8f0" align="right">
                                    {{ $reason }}
                                </td>
                            </tr>
                        </table>

                        <p style="margin: 24px 0 0; font-size: 14px; line-height: 22px; color: #64748b">
                            {{ __('marketplace.wallet.settlement_notice.error.outro') }}
                        </p>

                    </td>
                </tr>

            </table>
        </td>
    </tr>
</x-layouts.email>
