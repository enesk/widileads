{{--
    Tag 1 der Onboarding-Strecke (LP-ONBOARD-014): die drei Zustaende der
    Abrechnung aus LP-ONBOARD-010, hier als drei Zeilen mit Symbol.

    Die Symbole sind Zeichen und keine Bilder: Ein geblocktes Bild hinterlaesst
    eine leere Stelle, ein Zeichen kommt immer an. Gefuehrt werden sie in einer
    Tabelle mit zwei Spalten -- in Outlook ist das die einzige Art, etwas
    nebeneinander zu stellen.
--}}
@php
    $tint = config('app.email_color_tint');

    $states = [
        ['glyph' => '→', 'color' => $tint, 'key' => 'ringing', 'values' => []],
        ['glyph' => '✓', 'color' => '#15803d', 'key' => 'answered', 'values' => ['seconds' => $seconds]],
        ['glyph' => '↺', 'color' => '#b45309', 'key' => 'missed', 'values' => ['attempts' => $attempts, 'days' => $days, 'hours' => $hours]],
    ];
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('mail.onboarding.billing_explained.heading') }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('mail.onboarding.billing_explained.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('mail.onboarding.billing_explained.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        <p style="margin: 0 0 24px; line-height: 24px">
                            {{ __('mail.onboarding.billing_explained.intro', ['name' => $recipient->name]) }}
                        </p>

                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #e2e8f0; border-radius: 10px" cellpadding="0" cellspacing="0" role="none" bgcolor="#f8fafc">
                            @foreach ($states as $state)
                                <tr>
                                    <td width="56" style="padding: 16px 0 16px 20px; border-bottom: {{ $loop->last ? 'none' : '1px solid #e2e8f0' }}; vertical-align: top">
                                        <span style="display: inline-block; width: 28px; height: 28px; line-height: 28px; text-align: center; border-radius: 14px; background-color: #ffffff; border: 1px solid #e2e8f0; font-size: 15px; color: {{ $state['color'] }}">{{ $state['glyph'] }}</span>
                                    </td>
                                    <td style="padding: 16px 20px 16px 12px; border-bottom: {{ $loop->last ? 'none' : '1px solid #e2e8f0' }}; vertical-align: top">
                                        <p style="margin: 0 0 4px; font-size: 15px; font-weight: 600; color: #0f172a">
                                            {{ __('mail.onboarding.billing_explained.states.'.$state['key'].'.title', $state['values']) }}
                                        </p>
                                        <p style="margin: 0; font-size: 14px; line-height: 21px; color: #64748b">
                                            {{ __('mail.onboarding.billing_explained.states.'.$state['key'].'.text', $state['values']) }}
                                        </p>
                                    </td>
                                </tr>
                            @endforeach
                        </table>

                        <table style="width: 100%; margin-top: 32px" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td align="center">
                                    <a href="{{ $marketplaceUrl }}" style="display: inline-block; border-radius: 10px; background-color: {{ $tint }}; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none">
                                        {{ __('mail.onboarding.billing_explained.cta') }}
                                    </a>
                                </td>
                            </tr>
                        </table>

                        @include('emails.onboarding.partials.footer')
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</x-layouts.email>
