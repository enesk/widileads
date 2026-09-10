{{--
    FB-092: Bestellbestaetigung fuer den Guthabenkauf.

    Aufbau wie die Lead-Benachrichtigung: getoenter Kopf, weisse Karte,
    Tabellen und Inline-Styles -- Outlook kennt weder Flexbox noch Grid, und
    externe Stylesheets werden von den meisten Postfaechern entfernt.
--}}
@php
    $tint = config('app.email_color_tint');
    $currencyCode = $order->currency?->code;
    $hasDiscount = (int) $order->total_discount_amount > 0;
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('marketplace.credit.mail.heading') }} — {{ $order->uuid }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                {{-- Kopf: worum es geht. --}}
                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('marketplace.credit.mail.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('marketplace.credit.mail.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 8px; line-height: 24px">
                            {{ __('marketplace.credit.mail.intro') }}
                        </p>

                        <p style="margin: 0 0 28px; font-size: 13px; color: #94a3b8">
                            {{ __('marketplace.credit.mail.order_number') }}: {{ $order->uuid }}
                            @if ($order->created_at !== null)
                                &middot;
                                {{ __('marketplace.credit.mail.date') }}
                                {{ $order->created_at->translatedFormat('d. F Y') }}
                            @endif
                        </p>

                        <h2 style="margin: 0 0 12px; font-size: 13px; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase; color: #64748b">
                            {{ __('marketplace.credit.mail.items_heading') }}
                        </h2>

                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #e2e8f0; border-radius: 10px" cellpadding="0" cellspacing="0" role="none" bgcolor="#f8fafc">
                            @foreach ($order->items as $item)
                                <tr>
                                    <td style="padding: 12px 20px; border-bottom: {{ $loop->last ? 'none' : '1px solid #e2e8f0' }}; vertical-align: top">
                                        <div style="font-size: 16px; font-weight: 600; color: #0f172a">
                                            {{ $item->oneTimeProduct->name }}
                                        </div>
                                        @if ($item->oneTimeProduct->description)
                                            <div style="margin-top: 4px; font-size: 13px; line-height: 20px; color: #64748b">
                                                {{ $item->oneTimeProduct->description }}
                                            </div>
                                        @endif
                                        <div style="margin-top: 4px; font-size: 13px; color: #94a3b8">
                                            {{ __('marketplace.credit.mail.quantity', ['count' => $item->quantity]) }}
                                        </div>
                                    </td>
                                    <td style="padding: 12px 20px 12px 0; border-bottom: {{ $loop->last ? 'none' : '1px solid #e2e8f0' }}; font-size: 16px; color: #0f172a; text-align: right; vertical-align: top" align="right">
                                        @money($item->price_per_unit * $item->quantity, $currencyCode)
                                    </td>
                                </tr>
                            @endforeach
                        </table>

                        {{-- Summe: bei Rabatt zaehlt der Betrag, der abgebucht wurde. --}}
                        <table style="width: 100%; margin-top: 16px" cellpadding="0" cellspacing="0" role="none">
                            @if ($hasDiscount)
                                <tr>
                                    <td style="padding: 4px 0; font-size: 14px; color: #64748b">
                                        {{ __('marketplace.credit.mail.subtotal') }}
                                    </td>
                                    <td style="padding: 4px 0; font-size: 14px; color: #64748b; text-align: right" align="right">
                                        @money($order->total_amount, $currencyCode)
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0; font-size: 14px; color: #64748b">
                                        {{ __('marketplace.credit.mail.discount') }}
                                    </td>
                                    <td style="padding: 4px 0; font-size: 14px; color: #64748b; text-align: right" align="right">
                                        -@money($order->total_discount_amount, $currencyCode)
                                    </td>
                                </tr>
                            @endif
                            <tr>
                                <td style="padding: 8px 0 0; font-size: 16px; font-weight: 700; color: #0f172a">
                                    {{ __('marketplace.credit.mail.total') }}
                                </td>
                                <td style="padding: 8px 0 0; font-size: 16px; font-weight: 700; color: #0f172a; text-align: right" align="right">
                                    @money($order->total_amount_after_discount, $currencyCode)
                                </td>
                            </tr>
                        </table>

                        @if ($marketplaceUrl !== null)
                            <table style="width: 100%; margin-top: 32px" cellpadding="0" cellspacing="0" role="none">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $marketplaceUrl }}" style="display: inline-block; border-radius: 10px; background-color: {{ $tint }}; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none">
                                            {{ __('marketplace.credit.mail.cta') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0">&zwj;</div>

                        <p style="margin: 0 0 8px; font-size: 14px; line-height: 22px; color: #64748b">
                            {!! __('marketplace.credit.mail.support', [
                                'email' => '<a href="mailto:'.e(config('app.support_email')).'" style="color: '.$tint.'; text-decoration: none">'.e(config('app.support_email')).'</a>',
                            ]) !!}
                        </p>

                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #64748b">
                            {{ __('marketplace.credit.mail.outro', ['app' => config('app.wordmark')]) }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</x-layouts.email>
