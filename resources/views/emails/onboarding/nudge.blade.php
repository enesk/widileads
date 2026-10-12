{{--
    Tag 7 der Onboarding-Strecke (LP-ONBOARD-014): der letzte Anstupser.

    Der Ring-Fortschritt des Dashboards steht hier als Zahl und als
    Balkentabelle -- ein gezeichneter Ring waere entweder ein Bild, das
    geblockt wird, oder SVG, das die Haelfte der Postfaecher verschluckt. Der
    Balken ist eine Tabelle mit zwei Zellen und kommt ueberall an.

    Ein Knopf, und zwar auf den naechsten offenen Schritt.
--}}
@php
    $tint = config('app.email_color_tint');
    $url = $action['url'] ?? $marketplaceUrl;
    $label = $action['label'] ?? __('mail.onboarding.nudge.cta_marketplace');
    $percent = $total > 0 ? (int) round($done / $total * 100) : 0;
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('mail.onboarding.nudge.heading', ['done' => $done, 'total' => $total]) }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('mail.onboarding.nudge.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('mail.onboarding.nudge.heading', ['done' => $done, 'total' => $total]) }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">

                        {{-- Der Fortschritt als Balken: zwei Zellen, feste Prozente. --}}
                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 6px; overflow: hidden; margin: 0 0 24px" cellpadding="0" cellspacing="0" role="none" bgcolor="#e2e8f0">
                            <tr>
                                <td width="{{ $percent }}%" style="height: 10px; line-height: 10px; font-size: 0; background-color: {{ $tint }}" bgcolor="{{ $tint }}">&zwj;</td>
                                <td width="{{ 100 - $percent }}%" style="height: 10px; line-height: 10px; font-size: 0">&zwj;</td>
                            </tr>
                        </table>

                        <p style="margin: 0 0 24px; line-height: 24px">
                            {{ __('mail.onboarding.nudge.intro', ['name' => $recipient->name]) }}
                        </p>

                        @if ($action !== null)
                            <h2 style="margin: 0 0 12px; font-size: 13px; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase; color: #64748b">
                                {{ __('mail.onboarding.nudge.next_heading') }}
                            </h2>
                        @endif

                        <table style="width: 100%; margin-top: 16px" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td align="center">
                                    <a href="{{ $url }}" style="display: inline-block; border-radius: 10px; background-color: {{ $tint }}; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none">
                                        {{ $label }}
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin: 16px 0 0; font-size: 13px; line-height: 20px; color: #94a3b8">
                            {{ __('mail.fallback') }}<br>
                            <a href="{{ $url }}" style="color: {{ $tint }}; word-break: break-all">{{ $url }}</a>
                        </p>

                        @include('emails.onboarding.partials.footer')
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</x-layouts.email>
