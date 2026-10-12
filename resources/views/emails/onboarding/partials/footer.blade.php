{{--
    Fuss der Onboarding-Mails (LP-ONBOARD-014): Gruss und Abmeldelink.

    Der Abmeldelink gilt nur fuer diese Strecke -- das steht als Satz daneben,
    damit niemand glaubt, er schalte damit seine Kaufbestaetigungen ab.
--}}
<div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0">&zwj;</div>

<p style="margin: 0 0 16px; font-size: 14px; line-height: 22px; color: #64748b">
    {{ __('mail.closing', ['app' => config('app.wordmark')]) }}
</p>

<p style="margin: 0; font-size: 12px; line-height: 20px; color: #94a3b8">
    {{ __('mail.onboarding.unsubscribe.note') }}
    <a href="{{ $unsubscribeUrl }}" style="color: #94a3b8; text-decoration: underline">{{ __('mail.onboarding.unsubscribe.link') }}</a>
</p>
