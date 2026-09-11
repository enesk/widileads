{{--
    LP-WALLET-010: Entscheidung ueber eine Auszahlung, an den Verkaeufer.

    Eine Ansicht fuer beide Ausgaenge: `$status` waehlt die Sprachschluessel,
    der Aufbau ist derselbe. Bei einer Ablehnung kommt die Begruendung dazu --
    ohne sie waere die Rueckbuchung fuer den Verkaeufer nicht erklaerbar.
--}}
@php
    $tint = config('app.email_color_tint');
    $prefix = 'marketplace.wallet.payout.mail.processed.'.$status;
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __($prefix.'.heading') }} — {{ $amount }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('marketplace.wallet.payout.mail.processed.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __($prefix.'.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 28px; line-height: 24px">
                            {{ __($prefix.'.intro', ['amount' => $amount]) }}
                        </p>

                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 10px; border: 1px solid #e2e8f0" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; width: 60%">
                                    {{ __('marketplace.wallet.payout.mail.amount_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 16px; font-weight: 700; color: #0f172a" align="right">
                                    {{ $amount }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0">
                                    {{ __('marketplace.wallet.payout.mail.iban_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 16px; font-weight: 600; color: #0f172a; border-top: 1px solid #e2e8f0" align="right">
                                    {{ $iban }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0">
                                    {{ __('marketplace.wallet.payout.mail.reference_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 16px; font-weight: 600; color: #0f172a; border-top: 1px solid #e2e8f0" align="right">
                                    #{{ $payout->getKey() }}
                                </td>
                            </tr>
                        </table>

                        @if (filled($note))
                            <p style="margin: 24px 0 0; font-size: 13px; color: #64748b">
                                {{ __('marketplace.wallet.payout.mail.note_label') }}
                            </p>
                            <p style="margin: 4px 0 0; line-height: 24px">
                                {{ $note }}
                            </p>
                        @endif

                        <p style="margin: 24px 0 0; line-height: 24px">
                            {{ __($prefix.'.hint') }}
                        </p>

                    </td>
                </tr>

            </table>
        </td>
    </tr>
</x-layouts.email>
