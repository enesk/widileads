{{-- FB-020: Minimales Layout der oeffentlichen Funnel-Strecke.
     Bewusst ohne SaasyKit-Navigation, Footer und Marketing-Elemente: Der
     Endkunde soll den Funnel ausfuellen und nichts anderes -- kein Cookie-Banner
     der Hauptseite, keine fremden Links aus dem iFrame heraus.

     FB-024: Im Embed-Modus (?embed=1) faellt der Seitenhintergrund weg, damit
     sich die Strecke in die einbettende Seite einfuegt, und die Hoehe wird per
     postMessage nach draussen gemeldet. --}}
@props(['title' => null, 'embedded' => false, 'embedOrigin' => null, 'funnelToken' => null])
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
<body class="h-full antialiased {{ $embedded ? 'bg-transparent' : 'bg-base-200' }}">
    <main @class([
        'mx-auto flex w-full max-w-xl flex-col',
        'min-h-full px-4 py-6 sm:py-10' => ! $embedded,
        'p-4' => $embedded,
    ])>
        {{ $slot }}
    </main>

    @vite(['resources/js/app.js'])
    @livewireScripts

    @if ($embedded)
        {{-- Meldet die eigene Hoehe an die einbettende Seite. Gesendet wird
             gezielt an deren Origin, nie an "*": Sonst koennte jede Seite
             mitlesen, die das iFrame in ein eigenes einbettet. --}}
        <script>
            (function () {
                var target = @json($embedOrigin);
                var token = @json($funnelToken);

                if (!target) {
                    return;
                }

                function send(type, extra) {
                    var message = Object.assign(
                        { source: 'widileads-funnel', version: 1, token: token, type: type },
                        extra || {}
                    );

                    window.parent.postMessage(message, target);
                }

                function reportHeight() {
                    var height = Math.ceil(document.body.scrollHeight);

                    // Der Beobachter feuert einmal, bevor ueberhaupt etwas
                    // gerendert ist. Diese Null zu melden liesse das iFrame
                    // beim Laden sichtbar zusammenklappen.
                    if (height > 0) {
                        send('resize', { height: height });
                    }
                }

                if (window.ResizeObserver) {
                    new ResizeObserver(reportHeight).observe(document.body);
                } else {
                    window.addEventListener('resize', reportHeight);
                }

                window.addEventListener('load', function () {
                    send('ready');
                    reportHeight();
                });

                document.addEventListener('funnel:submitted', function () {
                    send('submitted');
                });
            })();
        </script>
    @endif
</body>
</html>
