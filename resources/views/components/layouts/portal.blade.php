{{--
    Rahmen der oeffentlichen Portalseiten.

    Getrennt vom SaaSykit-Layout: Diese Seiten richten sich an Besucher, nicht
    an angemeldete Nutzer, und tragen Kopf und Fuss des Portals. Stil und
    Verhalten stehen in resources/css/portal.css und resources/js/portal.js --
    im Markup steht kein <style> und kein onclick.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="portal">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('components.layouts.partials.head')

    {{-- Schrift des Entwurfs. Der Rest des Kopfs kommt aus dem gemeinsamen Partial. --}}
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/js/portal.js'])
</head>
<body {{ $attributes->merge(['class' => 'font-portal bg-zinc-50 text-zinc-700 antialiased']) }}>

@include('pages.home.header')

{{-- Das Ankuendigungsband der oeffentlichen Seiten (#95, #105). Im Rahmen und
     nicht in home.blade.php: Die Platzierung "frontend" gilt fuer alle
     oeffentlichen Seiten, nicht nur fuer /. Randlos unter dem Kopf, damit der
     Hero die erste farbige Flaeche bleibt -- und nicht sticky, das Band
     scrollt weg. --}}
@if (config('funnel.features.announcements'))
    <livewire:announcement.view
        placement="frontend"
        :portal="true"
        wrapper-class="bg-zinc-100 border-b border-zinc-200"
        inner-class="container-portal py-2.5 sm:py-3"
        focus-after-dismiss="#portal-header"
    />
@endif

<main>
    {{ $slot }}
</main>

@include('pages.home.footer')

@include('components.layouts.partials.tail')

</body>
</html>
