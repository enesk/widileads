{{--
    FB-084 (Ticket #14): Erinnerung 24 h vor Ablauf der Frist zur
    Erreichbarkeitspruefung.

    Bewusst ohne Kontaktdaten und ohne Lead-Nummer: Die Mail meldet eine Frist,
    sie liefert keine Daten aus. Wer den Lead braucht, folgt dem Link ins
    Portal und ist dort angemeldet.
--}}
@php
    $tint = config('app.email_color_tint');
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('call.reminder.mail.heading') }} — {{ $reference }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('call.reminder.mail.reference_label') }}: {{ $reference }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('call.reminder.mail.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 16px; line-height: 24px">
                            {{ __('call.reminder.mail.intro') }}
                        </p>

                        @if ($deadlineAt !== null)
                            <p style="margin: 0 0 24px; font-size: 13px; color: #94a3b8">
                                {{ __('call.reminder.mail.deadline_at', [
                                    'date' => $deadlineAt->translatedFormat('d. F Y'),
                                    'time' => $deadlineAt->format('H:i'),
                                ]) }}
                            </p>
                        @endif

                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 10px; border: 1px solid #e2e8f0" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td style="padding: 12px 20px; font-size: 13px; color: #64748b; width: 60%">
                                    {{ __('call.reminder.mail.attempts_label') }}
                                </td>
                                <td style="padding: 12px 20px 12px 0; font-size: 16px; font-weight: 600; color: #0f172a">
                                    {{ __('call.reminder.mail.attempts_value', [
                                        'attempts' => $validFailedAttempts,
                                        'required' => $requiredAttempts,
                                    ]) }}
                                </td>
                            </tr>
                        </table>

                        <p style="margin: 24px 0 0; line-height: 24px; font-weight: 600; color: #0f172a">
                            {{ __('call.reminder.mail.warning', ['required' => $requiredAttempts]) }}
                        </p>

                        @if ($leadUrl !== null)
                            <table style="width: 100%; margin-top: 32px" cellpadding="0" cellspacing="0" role="none">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $leadUrl }}" style="display: inline-block; border-radius: 10px; background-color: {{ $tint }}; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none">
                                            {{ __('call.reminder.mail.cta') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        @endif

                    </td>
                </tr>

            </table>
        </td>
    </tr>
</x-layouts.email>
