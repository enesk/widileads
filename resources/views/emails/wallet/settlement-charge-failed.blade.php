{{--
    LP-POSTPAID-008: Meldung an den Kaeufer ueber einen gescheiterten Einzug.

    Zwei Faelle in einer Ansicht: erster Fehlschlag mit Datum des zweiten
    Versuchs, endgueltiger Fehlschlag mit Hinweis auf die Rueckstufung. Aufbau
    wie die uebrigen Portalmails -- Tabellen und Inline-Styles, weil Outlook
    weder Flexbox noch Grid kennt.
--}}
@php
    $tint = config('app.email_color_tint');
    $branch = $final ? 'failed_final' : 'failed_retry';
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('marketplace.wallet.settlement_notice.'.$branch.'.heading') }} — {{ $amount }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('marketplace.wallet.settlement_notice.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('marketplace.wallet.settlement_notice.'.$branch.'.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 28px; line-height: 24px">
                            {{ __('marketplace.wallet.settlement_notice.'.$branch.'.intro', [
                                'amount' => $amount,
                                'date' => $retryDate ?? '',
                            ]) }}
                        </p>

                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 10px; border: 1px solid #e2e8f0" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; width: 55%">
                                    {{ __('marketplace.wallet.settlement_notice.amount_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 16px; font-weight: 700; color: #0f172a" align="right">
                                    {{ $amount }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0">
                                    {{ __('marketplace.wallet.settlement_notice.method_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 16px; font-weight: 600; color: #0f172a; border-top: 1px solid #e2e8f0" align="right">
                                    {{ $method }}
                                </td>
                            </tr>
                            @if (! $final && $retryDate)
                                <tr>
                                    <td style="padding: 12px 20px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0">
                                        {{ __('marketplace.wallet.settlement_notice.failed_retry.date_label') }}
                                    </td>
                                    <td style="padding: 12px 20px 12px 0; font-size: 16px; font-weight: 600; color: #0f172a; border-top: 1px solid #e2e8f0" align="right">
                                        {{ $retryDate }}
                                    </td>
                                </tr>
                            @endif
                        </table>

                        <table style="margin-top: 28px" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td style="border-radius: 8px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                                    <a href="{{ $paymentMethodsUrl }}" style="display: inline-block; padding: 14px 28px; font-size: 15px; font-weight: 600; line-height: 1; color: #ffffff; text-decoration: none">
                                        {{ __('marketplace.wallet.settlement_notice.payment_method_cta') }}
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin: 24px 0 0; font-size: 14px; line-height: 22px; color: #64748b">
                            {{ __('marketplace.wallet.settlement_notice.'.$branch.'.outro') }}
                        </p>

                    </td>
                </tr>

            </table>
        </td>
    </tr>
</x-layouts.email>
