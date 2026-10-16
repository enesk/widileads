{{--
    Meldung an den Betreiber ueber Leads, die im Zustand `neu` liegengeblieben
    sind (Ticket #108).

    Aufbau wie die uebrigen internen Meldungen (emails/wallet/ledger-mismatch):
    Tabellen und Inline-Styles, weil Outlook weder Flexbox noch Grid kennt und
    die meisten Postfaecher externe Stylesheets entfernen.
--}}
@php
    $tint = config('app.email_color_tint');
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('funnel.lead.stuck_new.mail.heading') }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('funnel.lead.stuck_new.mail.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('funnel.lead.stuck_new.mail.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 8px; line-height: 24px">
                            {{ __('funnel.lead.stuck_new.mail.intro', ['hours' => $hours]) }}
                        </p>

                        <p style="margin: 0 0 28px; font-size: 13px; color: #94a3b8">
                            {{ __('funnel.lead.stuck_new.mail.found', ['count' => $total]) }}
                        </p>

                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #e2e8f0; border-radius: 10px" cellpadding="0" cellspacing="0" role="none" bgcolor="#f8fafc">
                            @foreach ($leads as $lead)
                                <tr>
                                    <td style="padding: 16px 20px; border-bottom: {{ $loop->last ? 'none' : '1px solid #e2e8f0' }}; font-size: 15px; color: #0f172a">
                                        <strong>{{ __('funnel.lead.stuck_new.mail.lead', ['id' => $lead['id']]) }}</strong>
                                        <span style="color: #64748b"> — {{ __('funnel.lead.stuck_new.mail.funnel', ['id' => $lead['funnel_id'] ?? '—']) }}</span>

                                        <p style="margin: 8px 0 0; font-size: 14px; line-height: 22px; color: #334155">
                                            {{ __('funnel.lead.stuck_new.mail.created', ['at' => $lead['created_at']]) }}
                                            — {{ __('funnel.lead.stuck_new.mail.age', ['hours' => $lead['age_hours']]) }}
                                        </p>
                                    </td>
                                </tr>
                            @endforeach
                        </table>

                        @if ($total > count($leads))
                            <p style="margin: 12px 0 0; font-size: 14px; line-height: 22px; color: #64748b">
                                {{ __('funnel.lead.stuck_new.mail.truncated', ['count' => $total - count($leads)]) }}
                            </p>
                        @endif

                        <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0">&zwj;</div>

                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #64748b">
                            {{ __('funnel.lead.stuck_new.mail.outro') }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</x-layouts.email>
