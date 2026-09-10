{{--
    FB-091: Meldung an den Betreiber ueber einen neuen Lead.

    Kontaktdaten kommen ausschliesslich aus dem LeadContact, den der Listener
    vom LeadContactResolver geholt hat. Diese Ansicht entscheidet nichts, sie
    gibt aus -- eine zweite Maskierung hier waere die Stelle, an der irgendwann
    jemand ein Feld vergisst.
--}}
<x-layouts.email>
    <x-slot name="preview">
        {{ __('leads.notification.mail.heading') }}
    </x-slot>

    <tr>
        <td class="sm-px-6" style="border-radius: 4px; padding: 48px; font-size: 16px; color: #334155; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" bgcolor="#ffffff">
            <h1 class="sm-leading-8" style="margin: 0 0 24px; font-size: 24px; font-weight: 600; color: #000">
                {{ __('leads.notification.mail.heading') }}
            </h1>

            <p style="margin: 0 0 16px; line-height: 24px">
                {{ __('leads.notification.mail.intro', [
                    'funnel' => $lead->funnel?->name ?? __('leads.notification.mail.unknown_funnel'),
                ]) }}
            </p>

            <h2 style="margin: 24px 0 12px; font-size: 18px; font-weight: 600; color: #000">
                {{ __('leads.notification.mail.contact_heading') }}
            </h2>

            <p style="margin: 0 0 16px; line-height: 24px">
                <strong>{{ __('leads.notification.mail.name') }}:</strong>
                {{ $contact->fullName() ?? __('leads.contact.unknown') }}<br>

                <strong>{{ __('leads.notification.mail.email') }}:</strong>
                {{ $contact->email ?? __('leads.contact.missing') }}<br>

                <strong>{{ __('leads.notification.mail.phone') }}:</strong>
                {{ $contact->phone ?? __('leads.contact.missing') }}<br>

                <strong>{{ __('leads.notification.mail.postal_code') }}:</strong>
                {{ $contact->postalCode ?? __('leads.contact.missing') }}
            </p>

            @if ($answers !== [])
                <h2 style="margin: 24px 0 12px; font-size: 18px; font-weight: 600; color: #000">
                    {{ __('leads.notification.mail.answers_heading') }}
                </h2>

                <table style="width: 100%; border-collapse: collapse; margin: 0 0 16px">
                    @foreach ($answers as $label => $value)
                        <tr>
                            <td style="padding: 6px 12px 6px 0; vertical-align: top; line-height: 22px; color: #64748b; width: 45%">
                                {{ $label }}
                            </td>
                            <td style="padding: 6px 0; vertical-align: top; line-height: 22px">
                                {{ $value !== '' ? $value : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif

            <p style="margin: 0; line-height: 24px">
                {{ __('leads.notification.mail.outro') }}
            </p>
        </td>
    </tr>
</x-layouts.email>
