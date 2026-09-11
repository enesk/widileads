{{--
    Rahmen des angemeldeten Portal-Arbeitsbereichs (Portal Phase 1).

    Getrennt von x-layouts.portal: Das ist der Rahmen der oeffentlichen Seiten
    mit Marketingkopf und grossem Fuss. Hier sitzt stattdessen der Aufbau aus
    dem Entwurf -- fester Kopf, Navigationsspalte, Inhalt daneben, auf schmalen
    Bildschirmen eine Schublade. Einen Fuss hat der Arbeitsbereich nicht, der
    Entwurf zeigt keinen.

    Stil und Verhalten stehen in resources/css/portal.css und
    resources/js/portal.js -- im Markup steht kein <style> und kein onclick.

    Die Klasse "portal" am <html> stellt die Basisschriftgroesse auf 125 % --
    genau wie in den Entwuerfen, die alle `html{font-size:125%}` mitbringen.
    Ohne sie rechnet der Browser jedes rem auf 16 statt 20 Pixel, und damit
    faellt nicht nur die Schrift kleiner aus, sondern auch jeder Abstand, jede
    Ecke und jede Knopfhoehe -- der ganze Aufbau schrumpft um ein Fuenftel.
    Die Klasse "font-portal" am <body> bleibt: an ihr haengen Karte, Knopf,
    Pille und Eingabefeld.

    Erwartete Daten: $slot. Optional: $title (Seitentitel), $tenant (sonst der
    Mandant aus dem Pfad).
--}}
@props(['tenant' => null])

@php
    $tenant = $tenant ?? request()->route('tenant');
    $navigation = $tenant ? \App\Support\PortalNavigation::forTenant($tenant) : [];
    $role = $tenant?->isBuyer() ? __('portal.workspace.buyer') : __('portal.workspace.seller');
    $wallet = $tenant?->wallet;
    $user = auth()->user();
@endphp

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
<body class="font-portal bg-zinc-50 text-zinc-700 antialiased">

<a href="#portal-content" class="sr-only focus:not-sr-only focus:absolute focus:m-2 focus:z-50 btn-secondary">{{ __('portal.skip_to_content') }}</a>

<header class="sticky top-0 z-40 bg-white border-b border-zinc-200">
    <div class="flex items-center justify-between h-16 px-4 md:px-6">
        <div class="flex items-center gap-3">
            <button type="button" class="lg:hidden btn-ghost px-3" aria-label="{{ __('portal.open_menu') }}" aria-expanded="false" aria-controls="drawer" data-menu-toggle="drawer">
                <x-app.icon name="menu" />
            </button>

            <a href="{{ $tenant ? route('portal.overview', ['tenant' => $tenant->uuid]) : url('/portal') }}" class="flex items-center gap-2 font-semibold text-lg text-zinc-900">
                <span class="size-8 rounded-lg bg-brand text-white flex items-center justify-center">
                    <x-app.icon name="phone" class="size-4 shrink-0" />
                </span>
                widileads
            </a>
        </div>

        <div class="flex items-center gap-3">
            @if ($wallet && $tenant)
                <a href="{{ route('portal.wallet', ['tenant' => $tenant->uuid]) }}" class="pill hidden sm:inline-flex hover:bg-zinc-200">
                    {{ \App\Support\Money::format($wallet->available_cents) }}
                </a>
            @endif

            {{-- Kontomenue (Ticket #6). Profil und Abmelden liegen weiter im
                 Dashboard-Panel beziehungsweise an der bestehenden
                 Abmelderoute -- das Portal bringt dafuer keine eigenen Seiten
                 mit. --}}
            <x-app.dropdown align="right" :label="__('portal.account')">
                <x-slot:trigger class="size-10 rounded-full bg-brand text-white font-semibold flex items-center justify-center">
                    {{ mb_strtoupper(mb_substr($user?->name ?? '', 0, 1)) }}
                </x-slot:trigger>

                <div class="px-3 py-2 border-b border-zinc-200">
                    <p class="font-semibold text-zinc-900 truncate">{{ $user?->name }}</p>
                    <p class="text-xs text-zinc-500 truncate">{{ $user?->email }}</p>
                </div>

                @if ($tenant)
                    <a href="{{ route('filament.dashboard.pages.my-profile', ['tenant' => $tenant->uuid]) }}" role="menuitem" class="flex items-center min-h-11 px-3 rounded-lg text-sm text-zinc-700 hover:bg-zinc-100">
                        {{ __('portal.menu.profile') }}
                    </a>

                    <a href="{{ route('portal.settings', ['tenant' => $tenant->uuid]) }}" role="menuitem" class="flex items-center min-h-11 px-3 rounded-lg text-sm text-zinc-700 hover:bg-zinc-100">
                        {{ __('portal.menu.settings') }}
                    </a>
                @endif

                <form method="POST" action="{{ route('logout') }}" class="border-t border-zinc-200 mt-1 pt-1">
                    @csrf
                    <button type="submit" role="menuitem" class="w-full flex items-center min-h-11 px-3 rounded-lg text-sm text-zinc-700 hover:bg-zinc-100 text-left">
                        {{ __('portal.menu.logout') }}
                    </button>
                </form>
            </x-app.dropdown>
        </div>
    </div>
</header>

@if ($tenant)
    {{-- Schublade fuer schmale Bildschirme. Die Flaeche dahinter traegt
         denselben Schalter wie der Knopf im Kopf und schliesst sie damit. --}}
    <div id="drawer" class="hidden lg:hidden fixed inset-0 z-50">
        <div class="absolute inset-0 bg-zinc-900/40" data-menu-toggle="drawer" aria-hidden="true"></div>
        <aside class="absolute inset-y-0 left-0 w-80 max-w-[85%] bg-white overflow-y-auto">
            <x-app.sidebar :navigation="$navigation" :tenant="$tenant" :role="$role" />
        </aside>
    </div>
@endif

<div class="lg:grid lg:grid-cols-[18rem_1fr]">
    @if ($tenant)
        <aside class="hidden lg:block sticky top-16 h-[calc(100vh-4rem)] overflow-y-auto bg-white border-r border-zinc-200">
            <x-app.sidebar :navigation="$navigation" :tenant="$tenant" :role="$role" />
        </aside>
    @endif

    <main id="portal-content" class="px-4 py-6 md:px-8 md:py-10 max-w-7xl">
        {{ $slot }}
    </main>
</div>

{{-- Kurzmeldungen. Einmal im Rahmen, nicht je Seite: Jede Komponente
     schickt ihre Bestaetigung an denselben Ort (x-app.toasts). --}}
<x-app.toasts />

@include('components.layouts.partials.tail', ['skipCookieContentBar' => true])

</body>
</html>
