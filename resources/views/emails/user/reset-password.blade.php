{{-- Passwort zuruecksetzen. Aufbau wie die uebrigen Portalmails. --}}
@php
    $tint = config('app.email_color_tint');
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('mail.reset_password.heading') }}
    </x-slot>

    <tr>
        <td>
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" cellpadding="0" cellspacing="0" role="none" bgcolor="#ffffff">

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; background-color: {{ $tint }}" bgcolor="{{ $tint }}">
                        <p style="margin: 0 0 6px; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255, 255, 255, 0.75)">
                            {{ __('mail.reset_password.label') }}
                        </p>
                        <h1 class="sm-leading-8" style="margin: 0; font-size: 24px; font-weight: 700; line-height: 32px; color: #ffffff">
                            {{ __('mail.reset_password.heading') }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td class="sm-px-6" style="padding: 32px 40px; font-size: 16px; color: #334155">
                        <p style="margin: 0; font-size: 16px; line-height: 24px; color: #334155">
                            {{ __('mail.reset_password.intro') }}
                        </p>
                        <table style="width: 100%; margin: 28px 0" cellpadding="0" cellspacing="0" role="none">
                            <tr>
                                <td align="center">
                                    <a href="{{ $url }}" style="display: inline-block; border-radius: 10px; background-color: {{ $tint }}; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none">
                                        {{ __('mail.reset_password.cta') }}
                                    </a>
                                </td>
                            </tr>
                        </table>
                        <p style="margin: 0 0 16px; font-size: 14px; line-height: 24px; color: #64748b">
                            {{ __('mail.reset_password.expiry') }}<br>
                            {{ __('mail.reset_password.ignore') }}
                        </p>
                        <p style="margin: 0; font-size: 13px; line-height: 20px; color: #94a3b8">
                            {{ __('mail.fallback') }}<br>
                            <a href="{{ $url }}" style="color: {{ $tint }}; word-break: break-all">{{ $url }}</a>
                        </p>

                        <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0">&zwj;</div>

                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #64748b">
                            {{ __('mail.closing', ['app' => config('app.wordmark')]) }}
                        </p>

                    </td>
                </tr>
            </table>
        </td>
    </tr>
</x-layouts.email>
