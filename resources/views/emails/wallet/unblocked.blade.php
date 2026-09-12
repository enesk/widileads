{{--
    LP-POSTPAID-009: Die Kaufsperre ist aufgehoben.

    Bewusst kurz und ohne Rueckblick auf den Grund der Sperre. Aufbau wie die
    uebrigen Portalmails -- Tabellen und Inline-Styles, weil Outlook weder
    Flexbox noch Grid kennt.
--}}
@php
    $tint = config('app.email_color_tint');
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('marketplace.wallet.unblocked.mail.heading') }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('marketplace.wallet.unblocked.mail.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('marketplace.wallet.unblocked.mail.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 28px; line-height: 24px">
                            {{ __('marketplace.wallet.unblocked.mail.intro', ['balance' => $balance]) }}
                        </p>

                        <table cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td style="border-radius: 8px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                                    <a href="{{ $marketplaceUrl }}" style="display: inline-block; padding: 14px 28px; font-size: 15px; font-weight: 600; line-height: 1; color: #ffffff; text-decoration: none">
                                        {{ __('marketplace.wallet.unblocked.mail.cta') }}
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin: 24px 0 0; font-size: 14px; line-height: 22px; color: #64748b">
                            {{ __('marketplace.wallet.unblocked.mail.outro') }}
                        </p>

                    </td>
                </tr>

            </table>
        </td>
    </tr>
</x-layouts.email>
