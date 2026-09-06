{{--
    FB-030a: Anzeige von docs/openapi.yaml.

    Bewusst eine eigenstaendige Seite ohne das App-Layout: der Betrachter
    beansprucht das gesamte Fenster. Er wird ueber ein CDN geladen und nicht als
    Abhaengigkeit installiert -- das Erzeugnis dieses Tickets ist die
    Spezifikation, nicht ihre Darstellung.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('funnel.api_docs.title') }}</title>
</head>
<body>
    <noscript>
        <p>{{ __('funnel.api_docs.noscript') }}</p>
        <p><a href="{{ $specUrl }}">{{ __('funnel.api_docs.download') }}</a></p>
    </noscript>

    <script id="api-reference" data-url="{{ $specUrl }}"></script>
    <script src="{{ $viewerUrl }}" crossorigin="anonymous"></script>
</body>
</html>
