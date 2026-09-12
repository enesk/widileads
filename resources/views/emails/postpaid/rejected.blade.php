{{--
    LP-POSTPAID-006: Ablehnung eines Antrags auf Pay as you go.

    Bewusst ohne Detailbegruendung -- wer erfaehrt, an welcher Zahl es lag,
    kann genau diese Zahl herstellen. Genannt wird, was der Kaeufer wissen
    muss: dass er weiterhin mit Guthaben kauft und wann er erneut beantragen
    darf.
--}}
@php
    $tint = config('app.email_color_tint');
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('marketplace.wallet.postpaid.mail.rejected.heading') }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('marketplace.wallet.postpaid.mail.rejected.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('marketplace.wallet.postpaid.mail.rejected.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 20px; line-height: 24px">
                            {{ __('marketplace.wallet.postpaid.mail.rejected.intro') }}
                        </p>

                        <p style="margin: 0 0 20px; line-height: 24px">
                            {{ __('marketplace.wallet.postpaid.mail.rejected.retry_hint', ['days' => $retryDays]) }}
                        </p>

                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #64748b">
                            {{ __('marketplace.wallet.postpaid.mail.rejected.support_hint') }}
                        </p>

                    </td>
                </tr>

            </table>
        </td>
    </tr>
</x-layouts.email>
