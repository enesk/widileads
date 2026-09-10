{{--
    Meldung an den Support ueber eine neue Reklamation.

    Aufbau wie die Lead-Meldung (emails/lead/received.blade.php): Tabellen und
    Inline-Styles, weil Outlook weder Flexbox noch Grid kennt und die meisten
    Postfaecher externe Stylesheets entfernen.

    Kontaktdaten des Leads stehen hier nicht -- fuer die Entscheidung braucht
    sie niemand.
--}}
@php
    $tint = config('app.email_color_tint');
    $purchase = $complaint->purchase;
    $lead = $complaint->lead;
    $rows = [
        __('marketplace.complaint.mail.lead') => '#'.$complaint->lead_id,
        __('leads.list.funnel') => $lead?->funnel?->name ?? __('marketplace.listing.unknown_funnel'),
        __('marketplace.complaint.mail.buyer') => $complaint->buyer?->name ?? '—',
        __('marketplace.complaint.fields.requested_state') => $complaint->requested_state->label(),
        __('marketplace.purchased.csv.purchased_at') => $purchase?->purchased_at?->format('d.m.Y H:i') ?? '—',
    ];
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('marketplace.complaint.mail.heading') }} — #{{ $complaint->lead_id }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('marketplace.complaint.mail.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('marketplace.complaint.mail.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 8px; line-height: 24px">
                            {{ __('marketplace.complaint.mail.intro') }}
                        </p>

                        <p style="margin: 0 0 28px; font-size: 13px; color: #94a3b8">
                            {{ __('marketplace.complaint.mail.filed_at', [
                                'date' => $complaint->created_at?->translatedFormat('d. F Y'),
                                'time' => $complaint->created_at?->format('H:i'),
                            ]) }}
                        </p>

                        <h2 style="margin: 0 0 12px; font-size: 13px; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase; color: #64748b">
                            {{ __('marketplace.complaint.mail.facts_heading') }}
                        </h2>

                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #e2e8f0; border-radius: 10px" cellpadding="0" cellspacing="0" role="none" bgcolor="#f8fafc">
                            @foreach ($rows as $label => $value)
                                <tr>
                                    <td style="padding: 12px 20px; border-bottom: {{ $loop->last ? 'none' : '1px solid #e2e8f0' }}; font-size: 13px; color: #64748b; width: 38%; vertical-align: top">
                                        {{ $label }}
                                    </td>
                                    <td style="padding: 12px 20px 12px 0; border-bottom: {{ $loop->last ? 'none' : '1px solid #e2e8f0' }}; font-size: 16px; font-weight: 600; color: #0f172a; vertical-align: top">
                                        {{ $value }}
                                    </td>
                                </tr>
                            @endforeach
                        </table>

                        <h2 style="margin: 32px 0 12px; font-size: 13px; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase; color: #64748b">
                            {{ __('marketplace.complaint.fields.reason') }}
                        </h2>

                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-left: 3px solid {{ $tint }}" cellpadding="0" cellspacing="0" role="none" bgcolor="#f8fafc">
                            <tr>
                                <td style="padding: 16px 20px; font-size: 15px; line-height: 24px; color: #0f172a">
                                    {{ $complaint->reason }}
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; margin-top: 32px" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td align="center">
                                    <a href="{{ route('filament.admin.resources.lead-complaints.index') }}" style="display: inline-block; border-radius: 10px; background-color: {{ $tint }}; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none">
                                        {{ __('marketplace.complaint.mail.cta') }}
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0">&zwj;</div>

                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #64748b">
                            {{ __('marketplace.complaint.mail.outro') }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</x-layouts.email>
