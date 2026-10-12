{{--
    Der Rahmen des Onboardings (LP-ONBOARD-005).

    Vierter Portalrahmen neben portal.blade.php (oeffentlich),
    portal-focus.blade.php (anmelden, registrieren, Passwort) und
    portal-app.blade.php (angemeldeter Arbeitsbereich). Eigene Datei und nicht
    ein Schalter in portal-focus: Der Kopf ist hier kein weisser Balken mit
    Abbrechen-Link, sondern eine volle Markenflaeche mit Begruessung, und der
    Fuss ist auf eine Zeile eingekocht. Das als drei Bedingungen in den
    Anmelde-Rahmen zu legen heisst, bei jeder Aenderung an der Anmeldung die
    Einrichtung mitzutesten -- und umgekehrt.

    **Wer hier ist, kommt nur in einer Richtung weiter.** Es gibt keine
    Navigation, keinen Workspace-Wechsler, keine Guthaben-Anzeige und kein
    "Abbrechen": Die Sperre EnsurePhoneVerified fuehrt jede andere Adresse
    hierher zurueck, ein Ausweg waere also eine Schleife mit Zwischenstopp. Der
    einzige echte Ausweg ist "Abmelden", klein im Fuss -- eine Sperre, aus der
    man nicht herauskommt, ist eine Falle (siehe die Freiliste der Middleware).

    Volle Markenflaechen sind die ausgewiesene widileads-Abweichung vom Skill
    sun-portal-design.

    Die Klasse "portal" am html steht wie in portal-focus.blade.php: Die
    Einrichtung und der Wizard (LP-ONBOARD-007) sollen in derselben Schriftskala
    laufen, sonst springt die Schrift beim Wechsel vom Code-Screen in den ersten
    Schritt.
--}}
@props(['heading' => null])

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="portal">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('components.layouts.partials.head')

    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/js/portal.js'])
</head>
<body class="font-portal bg-zinc-50 text-zinc-700 antialiased min-h-screen flex flex-col">

{{-- Das Markenband. Kein Link auf die Startseite am Logo: Es ist hier Marke
     und kein Ausweg. --}}
<header class="bg-brand text-white">
    <div class="container-portal py-5 md:py-6 flex flex-col gap-1.5">
        <span class="flex items-center gap-2 font-semibold text-lg">
            <span class="size-8 rounded-lg bg-white/15 flex items-center justify-center shrink-0">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            </span>
            widileads
        </span>

        @if ($heading !== null)
            <p class="text-sm text-white/85">{{ $heading }}</p>
        @endif
    </div>
</header>

<main class="flex-1">
    {{ $slot }}
</main>

{{-- Mini-Fuss: der Ausweg und die Pflichtangaben. Die drei Rechtstexte stehen
     in der Freiliste von EnsurePhoneVerified und sind von hier aus erreichbar;
     alles andere fuehrt hierher zurueck. --}}
<footer class="border-t border-zinc-200 bg-white">
    <div class="container-portal py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-zinc-500">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="hover:text-zinc-900 underline">{{ __('portal.menu.logout') }}</button>
        </form>

        <div class="flex gap-4">
            <a href="{{ route('terms-of-service') }}" class="hover:text-zinc-900">{{ __('Terms of Service') }}</a>
            <a href="{{ route('privacy-policy') }}" class="hover:text-zinc-900">{{ __('Privacy Policy') }}</a>
        </div>
    </div>
</footer>

@include('components.layouts.partials.tail', ['skipCookieContentBar' => true])

</body>
</html>
