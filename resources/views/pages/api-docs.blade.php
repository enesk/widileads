{{--
    FB-030a: Anzeige von docs/openapi.yaml.
    FB-030h: dazu docs/openapi-public.yaml, umschaltbar in der Kopfleiste.

    Bewusst eine eigenstaendige Seite ohne das App-Layout: der Betrachter
    beansprucht das gesamte Fenster. Er wird ueber ein CDN geladen und nicht als
    Abhaengigkeit installiert -- das Erzeugnis dieses Tickets ist die
    Spezifikation, nicht ihre Darstellung. Aus demselben Grund steht das bisschen
    CSS hier direkt und nicht im Build der Anwendung.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('funnel.api_docs.title') }}</title>
    <style>
        body { margin: 0; }

        .spec-switch {
            display: flex;
            gap: .25rem;
            align-items: center;
            padding: .5rem .75rem;
            border-bottom: 1px solid #e2e8f0;
            font: 14px/1.4 ui-sans-serif, system-ui, sans-serif;
            background: #f8fafc;
        }

        .spec-switch a {
            padding: .35rem .7rem;
            border-radius: .375rem;
            color: #334155;
            text-decoration: none;
        }

        .spec-switch a:hover { background: #e2e8f0; }

        /* Die angezeigte Spezifikation ist nicht anklickbar -- sonst waere an
           der Leiste nicht zu erkennen, welche man gerade sieht. */
        .spec-switch strong {
            padding: .35rem .7rem;
            border-radius: .375rem;
            background: #1e293b;
            color: #fff;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <nav class="spec-switch" aria-label="{{ __('funnel.api_docs.title') }}">
        @foreach ($specifications as $specification)
            @if ($specification['key'] === $current)
                <strong aria-current="page">{{ $specification['label'] }}</strong>
            @else
                <a href="{{ $specification['url'] }}">{{ $specification['label'] }}</a>
            @endif
        @endforeach
    </nav>

    <noscript>
        <p>{{ __('funnel.api_docs.noscript') }}</p>
        <p><a href="{{ $specUrl }}">{{ __('funnel.api_docs.download') }}</a></p>
    </noscript>

    <script id="api-reference" data-url="{{ $specUrl }}"></script>
    <script src="{{ $viewerUrl }}" crossorigin="anonymous"></script>
</body>
</html>
