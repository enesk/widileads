{{--
    Bestaetigung der Abmeldung von der Onboarding-Strecke (LP-ONBOARD-014).

    Im Fokus-Rahmen und ohne Navigation: Wer hier landet, kommt aus einer Mail
    und hat genau eine Sache erledigt. Der Satz, dass Belege weiter kommen,
    ist kein Kleingedrucktes -- er beantwortet die Frage, die der Klick
    aufwirft.
--}}
<x-layouts.portal-focus>
    <div class="container-portal py-16">
        <div class="mx-auto max-w-md text-center">
            <x-ui.illustration name="success" class="mx-auto max-w-[200px]" />

            <h1 class="mt-8 text-2xl font-semibold text-zinc-900">
                {{ __('mail.onboarding.unsubscribe.heading') }}
            </h1>

            <p class="mt-3 text-sm leading-6 text-zinc-600">
                {{ __('mail.onboarding.unsubscribe.text') }}
            </p>

            <a href="{{ route('home') }}" class="btn-primary mt-8 inline-flex">
                {{ __('mail.onboarding.unsubscribe.back') }}
            </a>
        </div>
    </div>
</x-layouts.portal-focus>
