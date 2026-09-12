{{--
    LP-POSTPAID-009: Meldung ueber das Ende von Pay as you go.

    Dieselbe Ansicht fuer Kaeufer und Betreiber; fuer den Betreiber steht oben
    zusaetzlich der Name des Kaeufers. Aufbau wie die uebrigen Portalmails --
    Tabellen und Inline-Styles, weil Outlook weder Flexbox noch Grid kennt.
--}}
@php
    $tint = config('app.email_color_tint');
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('marketplace.wallet.postpaid.mail.downgraded.heading') }} — {{ $openAmount }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('marketplace.wallet.postpaid.mail.downgraded.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('marketplace.wallet.postpaid.mail.downgraded.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        @if ($forOperator)
                            <p style="margin: 0 0 20px; padding: 12px 16px; border-radius: 8px; background-color: #f1f5f9; font-size: 14px; line-height: 22px; color: #475569">
                                {{ __('marketplace.wallet.postpaid.mail.downgraded.operator_hint', ['buyer' => $buyerName]) }}
                            </p>
                        @endif

                        <p style="margin: 0 0 28px; line-height: 24px">
                            {{ __('marketplace.wallet.postpaid.mail.downgraded.intro') }}
                        </p>

                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 10px; border: 1px solid #e2e8f0" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; width: 55%">
                                    {{ __('marketplace.wallet.postpaid.mail.downgraded.reason_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 15px; font-weight: 600; color: #0f172a" align="right">
                                    {{ $reasonText }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0">
                                    {{ __('marketplace.wallet.postpaid.mail.downgraded.open_amount_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 16px; font-weight: 700; color: #0f172a; border-top: 1px solid #e2e8f0" align="right">
                                    {{ $openAmount }}
                                </td>
                            </tr>
                            @if ($fee)
                                <tr>
                                    <td style="padding: 12px 20px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0">
                                        {{ __('marketplace.wallet.postpaid.mail.downgraded.fee_label') }}
                                    </td>
                                    <td style="padding: 12px 20px 12px 0; font-size: 16px; font-weight: 600; color: #0f172a; border-top: 1px solid #e2e8f0" align="right">
                                        {{ $fee }}
                                    </td>
                                </tr>
                            @endif
                        </table>

                        <p style="margin: 28px 0 0; line-height: 24px">
                            {{ $blocked
                                ? __('marketplace.wallet.postpaid.mail.downgraded.blocked_hint')
                                : __('marketplace.wallet.postpaid.mail.downgraded.open_hint') }}
                        </p>

                        @if ($showDeadline)
                            <p style="margin: 16px 0 0; line-height: 24px">
                                {{ __('marketplace.wallet.postpaid.mail.downgraded.deadline_hint', ['days' => $settleWithinDays]) }}
                            </p>
                        @endif

                        <table style="margin-top: 28px" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td style="border-radius: 8px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                                    <a href="{{ $topUpUrl }}" style="display: inline-block; padding: 14px 28px; font-size: 15px; font-weight: 600; line-height: 1; color: #ffffff; text-decoration: none">
                                        {{ __('marketplace.wallet.postpaid.mail.downgraded.cta') }}
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin: 24px 0 0; font-size: 14px; line-height: 22px; color: #64748b">
                            {{ __('marketplace.wallet.postpaid.mail.downgraded.outro') }}
                        </p>

                    </td>
                </tr>

            </table>
        </td>
    </tr>
</x-layouts.email>
