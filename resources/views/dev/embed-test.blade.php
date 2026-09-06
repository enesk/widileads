{{-- FB-024: Testseite fuer das Embed-Snippet.
     Simuliert eine fremde Webseite: Sie kennt nur die Skript-URL und den Token,
     sonst nichts von der Anwendung. Nur in der lokalen Umgebung erreichbar. --}}
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Embed-Test</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f4f4f5; color: #18181b; }
        main { max-width: 720px; margin: 0 auto; padding: 24px 16px 64px; }
        section { background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 24px; }
        code { background: #ececee; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
        #log { font-family: ui-monospace, monospace; font-size: 12px; white-space: pre-wrap; }
    </style>
</head>
<body>
<main>
    <h1>Embed-Test</h1>

    @if ($token === null)
        <section>
            <p><strong>Kein veroeffentlichter Funnel gefunden.</strong></p>
            <p>Erst einen Funnel veroeffentlichen, dann diese Seite neu laden. Alternativ
                einen Token anhaengen: <code>?token=01J8Z…</code></p>
        </section>
    @else
        <section>
            <p>Eingebetteter Funnel: <code>{{ $token }}</code></p>
            <p>Diese Seite bindet ausschliesslich <code>{{ $scriptUrl }}</code> ein --
                so, wie es eine fremde Webseite taete.</p>
        </section>

        <h2>Inline</h2>
        <section>
            <script src="{{ $scriptUrl }}" data-funnel="{{ $token }}" data-height="520" async></script>
        </section>

        <h2>Overlay</h2>
        <section>
            <script src="{{ $scriptUrl }}" data-funnel="{{ $token }}" data-mode="overlay"
                    data-button-label="Pfotencheck starten" async></script>
        </section>

        <h2>Nachrichten aus dem iFrame</h2>
        <section><div id="log">(noch nichts empfangen)</div></section>

        <script>
            var log = document.getElementById('log');
            var lines = [];

            ['ready', 'resize', 'submitted', 'close'].forEach(function (type) {
                document.addEventListener('funnel:' + type, function (event) {
                    lines.unshift(new Date().toLocaleTimeString() + '  ' + type + '  ' + JSON.stringify(event.detail));
                    log.textContent = lines.slice(0, 20).join('\n');
                });
            });
        </script>
    @endif
</main>
</body>
</html>
