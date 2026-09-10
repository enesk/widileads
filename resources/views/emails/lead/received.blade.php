{{--
    FB-091: Meldung an den Betreiber ueber einen neuen Lead.

    Kontaktdaten kommen ausschliesslich aus dem LeadContact, den der Listener
    vom LeadContactResolver geholt hat. Diese Ansicht entscheidet nichts, sie
    gibt aus -- eine zweite Maskierung hier waere die Stelle, an der irgendwann
    jemand ein Feld vergisst.

    Gebaut mit Tabellen und Inline-Styles: Outlook kennt weder Flexbox noch
    Grid, und externe Stylesheets werden von den meisten Postfaechern entfernt.
--}}
@php
    $tint = config('app.email_color_tint');
    $funnelName = $lead->funnel?->name ?? __('leads.notification.mail.unknown_funnel');
    $leadUrl = $lead->funnel?->tenant === null ? null : route('filament.dashboard.resources.leads.view', [
        'tenant' => $lead->funnel->tenant,
        'record' => $lead,
    ]);
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('leads.notification.mail.heading') }} — {{ $contact->fullName() ?? $funnelName }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                {{-- Kopf: worum es geht, aus welchem Fragebogen. --}}
                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('leads.notification.mail.funnel_label') }}: {{ $funnelName }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('leads.notification.mail.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 8px; line-height: 24px">
                            {{ __('leads.notification.mail.intro') }}
                        </p>

                        <p style="margin: 0 0 28px; font-size: 13px; color: #94a3b8">
                            {{ __('leads.notification.mail.received_at', [
                                'date' => $lead->created_at?->translatedFormat('d. F Y'),
                                'time' => $lead->created_at?->format('H:i'),
                            ]) }}
                            @if ($lead->score !== null)
                                &middot; {{ __('leads.notification.mail.score', ['score' => $lead->score]) }}
                            @endif
                        </p>

                        {{-- Kontakt: das Wichtigste, deshalb hervorgehoben. --}}
                        <h2 style="margin: 0 0 12px; font-size: 13px; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase; color: #64748b">
                            {{ __('leads.notification.mail.contact_heading') }}
                        </h2>

                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #e2e8f0; border-radius: 10px" cellpadding="0" cellspacing="0" role="none" bgcolor="#f8fafc">
                            @php
                                // Verdeckte Werte bleiben Text: Ein Link auf
                                // "a...@example.com" fuehrt ins Leere und sieht
                                // aus wie ein Fehler.
                                $linkable = ! $contact->masked;

                                $contactRows = [
                                    __('leads.notification.mail.name') => [$contact->fullName() ?? __('leads.contact.unknown'), null],
                                    __('leads.notification.mail.email') => [$contact->email, $linkable && $contact->email !== null ? 'mailto:'.$contact->email : null],
                                    __('leads.notification.mail.phone') => [$contact->phone, $linkable && $contact->phone !== null ? 'tel:'.preg_replace('/[^\d+]/', '', $contact->phone) : null],
                                    __('leads.notification.mail.postal_code') => [$contact->postalCode, null],
                                ];
                            @endphp

                            @foreach ($contactRows as $label => [$value, $href])
                                <tr>
                                    <td style="padding: 12px 20px; border-bottom: {{ $loop->last ? 'none' : '1px solid #e2e8f0' }}; font-size: 13px; color: #64748b; width: 38%; vertical-align: top">
                                        {{ $label }}
                                    </td>
                                    <td style="padding: 12px 20px 12px 0; border-bottom: {{ $loop->last ? 'none' : '1px solid #e2e8f0' }}; font-size: 16px; font-weight: 600; color: #0f172a; vertical-align: top">
                                        @if ($value === null)
                                            <span style="font-weight: 400; color: #94a3b8">{{ __('leads.contact.missing') }}</span>
                                        @elseif ($href === null)
                                            {{ $value }}
                                        @else
                                            <a href="{{ $href }}" style="color: {{ $tint }}; text-decoration: none">{{ $value }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>

                        @if ($contact->masked)
                            <p style="margin: 12px 0 0; font-size: 13px; color: #94a3b8">
                                {{ __('leads.notification.mail.masked_hint') }}
                            </p>
                        @endif

                        {{-- Angaben aus dem Fragebogen. --}}
                        <h2 style="margin: 32px 0 12px; font-size: 13px; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase; color: #64748b">
                            {{ __('leads.notification.mail.answers_heading') }}
                        </h2>

                        @if ($answers === [])
                            <p style="margin: 0; line-height: 24px; color: #94a3b8">
                                {{ __('leads.notification.mail.no_answers') }}
                            </p>
                        @else
                            <table style="width: 100%; border-collapse: collapse" cellpadding="0" cellspacing="0" role="none">
                                @foreach ($answers as $label => $value)
                                    <tr bgcolor="{{ $loop->even ? '#f8fafc' : '#ffffff' }}">
                                        <td style="padding: 10px 16px; font-size: 14px; line-height: 22px; color: #64748b; width: 45%; vertical-align: top">
                                            {{ $label }}
                                        </td>
                                        <td style="padding: 10px 16px; font-size: 14px; line-height: 22px; color: #0f172a; vertical-align: top">
                                            {{ $value !== '' ? $value : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif

                        @if ($leadUrl !== null)
                            <table style="width: 100%; margin-top: 32px" cellpadding="0" cellspacing="0" role="none">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $leadUrl }}" style="display: inline-block; border-radius: 10px; background-color: {{ $tint }}; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none">
                                            {{ __('leads.notification.mail.cta') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0">&zwj;</div>

                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #64748b">
                            {{ __('leads.notification.mail.outro') }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</x-layouts.email>
