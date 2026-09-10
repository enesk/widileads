{{--
    Schlanker Portalrahmen fuer Seiten mit genau einer Aufgabe: anmelden,
    registrieren, Passwort zuruecksetzen.

    Gleiche Bausteine wie das grosse Portal-Layout, aber ohne Navigation: Wer
    hier ist, soll das Formular ausfuellen und nicht weiterstoebern. Kopf und
    Fuss bleiben schmal, der einzige Ausweg ist "Abbrechen".
--}}
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

<header class="bg-white border-b border-zinc-200">
    <div class="container-portal flex items-center justify-between h-16">
        <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold text-lg text-zinc-900">
            <span class="size-8 rounded-lg bg-brand text-white flex items-center justify-center">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            </span>
            widileads
        </a>
        <a href="{{ route('home') }}" class="btn-ghost">{{ __('Cancel') }}</a>
    </div>
</header>

<main class="flex-1">
    {{ $slot }}
</main>

<footer class="border-t border-zinc-200 bg-white">
    <div class="container-portal py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-zinc-500">
        <span>&copy; {{ date('Y') }} widileads</span>
        <div class="flex gap-4">
            <a href="{{ route('terms-of-service') }}" class="hover:text-zinc-900">{{ __('Terms of Service') }}</a>
            <a href="{{ route('privacy-policy') }}" class="hover:text-zinc-900">{{ __('Privacy Policy') }}</a>
        </div>
    </div>
</footer>

@include('components.layouts.partials.tail', ['skipCookieContentBar' => true])

</body>
</html>
