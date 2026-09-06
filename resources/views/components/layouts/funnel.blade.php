{{-- FB-020: Minimales Layout der oeffentlichen Funnel-Strecke.
     Bewusst ohne SaasyKit-Navigation, Footer und Marketing-Elemente: Der
     Endkunde soll den Funnel ausfuellen und nichts anderes. Eingebettet wird
     die Strecke spaeter per iFrame (FB-024), deshalb auch kein Cookie-Banner
     der Hauptseite. --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $title ?? config('app.name') }}</title>

    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('images/favicon.ico') }}">

    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="h-full bg-base-200 antialiased">
    <main class="mx-auto flex min-h-full w-full max-w-xl flex-col px-4 py-6 sm:py-10">
        {{ $slot }}
    </main>

    @vite(['resources/js/app.js'])
    @livewireScripts
</body>
</html>
