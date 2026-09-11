{{--
    Meldung an den Betreiber ueber abgewichene Wallet-Salden (LP-WALLET-015).

    Aufbau wie die uebrigen internen Meldungen (emails/lead/complaint-filed):
    Tabellen und Inline-Styles, weil Outlook weder Flexbox noch Grid kennt und
    die meisten Postfaecher externe Stylesheets entfernen.
--}}
@php
    $tint = config('app.email_color_tint');
    $currency = config('wallet.currency');
    $money = static fn (int $cents): string => number_format($cents / 100, 2, ',', '.').' '.$currency;
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('marketplace.wallet.verify.mail.heading') }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('marketplace.wallet.verify.mail.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('marketplace.wallet.verify.mail.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 8px; line-height: 24px">
                            {{ __('marketplace.wallet.verify.mail.intro') }}
                        </p>

                        <p style="margin: 0 0 28px; font-size: 13px; color: #94a3b8">
                            {{ __('marketplace.wallet.verify.mail.checked', ['count' => $walletsChecked]) }}
                        </p>

                        @if ($mismatches !== [])
                            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #e2e8f0; border-radius: 10px" cellpadding="0" cellspacing="0" role="none" bgcolor="#f8fafc">
                                @foreach ($mismatches as $mismatch)
                                    <tr>
                                        <td style="padding: 16px 20px; border-bottom: {{ $loop->last ? 'none' : '1px solid #e2e8f0' }}; font-size: 15px; color: #0f172a">
                                            <strong>{{ __('marketplace.wallet.verify.mail.wallet', ['id' => $mismatch['wallet_id']]) }}</strong>
                                            <span style="color: #64748b"> — {{ $mismatch['owner'] }}</span>

                                            <p style="margin: 8px 0 0; font-size: 14px; line-height: 22px; color: #334155">
                                                {{ __('marketplace.wallet.verify.mail.balance') }}:
                                                {{ __('marketplace.wallet.verify.mail.expected') }} {{ $money($mismatch['balance_expected']) }},
                                                {{ __('marketplace.wallet.verify.mail.actual') }} {{ $money($mismatch['balance_actual']) }}
                                            </p>
                                            <p style="margin: 4px 0 0; font-size: 14px; line-height: 22px; color: #334155">
                                                {{ __('marketplace.wallet.verify.mail.reserved') }}:
                                                {{ __('marketplace.wallet.verify.mail.expected') }} {{ $money($mismatch['reserved_expected']) }},
                                                {{ __('marketplace.wallet.verify.mail.actual') }} {{ $money($mismatch['reserved_actual']) }}
                                            </p>

                                            @if ($mismatch['repaired'])
                                                <p style="margin: 8px 0 0; font-size: 13px; color: #64748b">
                                                    {{ __('marketplace.wallet.verify.mail.repaired') }}
                                                </p>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif

                        @if ($reservationTotals !== null)
                            <table style="width: 100%; margin-top: 24px; border-collapse: separate; border-spacing: 0; border-left: 3px solid {{ $tint }}" cellpadding="0" cellspacing="0" role="none" bgcolor="#f8fafc">
                                <tr>
                                    <td style="padding: 16px 20px; font-size: 15px; line-height: 24px; color: #0f172a">
                                        {{ __('marketplace.wallet.verify.mail.reservation_totals', [
                                            'expected' => $money($reservationTotals['expected']),
                                            'actual' => $money($reservationTotals['actual']),
                                        ]) }}
                                    </td>
                                </tr>
                            </table>
                        @endif

                        <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0">&zwj;</div>

                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #64748b">
                            {{ __('marketplace.wallet.verify.mail.outro') }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</x-layouts.email>
