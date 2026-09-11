{{--
    Meldung an den Betreiber ueber einen nicht abgerechneten Leadkauf
    (LP-WALLET-008).

    Aufbau wie die uebrigen internen Meldungen (emails/wallet/ledger-mismatch):
    Tabellen und Inline-Styles, weil Outlook weder Flexbox noch Grid kennt und
    die meisten Postfaecher externe Stylesheets entfernen.
--}}
@php
    $tint = config('app.email_color_tint');
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('marketplace.wallet.settlement.mail.heading') }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('marketplace.wallet.settlement.mail.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('marketplace.wallet.settlement.mail.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 24px; line-height: 24px">
                            {{ __('marketplace.wallet.settlement.mail.intro', [
                                'lead' => $leadId,
                                'status' => $contactStatusLabel,
                            ]) }}
                        </p>

                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-left: 3px solid {{ $tint }}" cellpadding="0" cellspacing="0" role="none" bgcolor="#f8fafc">
                            <tr>
                                <td style="padding: 16px 20px; font-size: 14px; line-height: 22px; color: #0f172a">
                                    {{ __('marketplace.wallet.settlement.mail.reason') }}: {{ $reason }}
                                </td>
                            </tr>
                        </table>

                        <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0">&zwj;</div>

                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #64748b">
                            {{ __('marketplace.wallet.settlement.mail.outro') }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</x-layouts.email>
