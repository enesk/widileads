<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('builder.theme.preview') }}</title>
    <style>
        :root {
            --funnel-primary: {{ $primary }};
            --funnel-secondary: {{ $secondary }};
            --funnel-background: {{ $background }};
            --funnel-text: {{ $text }};
            --funnel-radius: {{ $radius }}px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            background: var(--funnel-background);
            color: var(--funnel-text);
            font-family: {{ $fontFamily }};
            font-size: 15px;
            line-height: 1.5;
        }
        .progress-bar { height: 8px; background: color-mix(in srgb, var(--funnel-secondary) 25%, transparent); border-radius: 999px; overflow: hidden; }
        .progress-bar > span { display: block; height: 100%; width: 60%; background: var(--funnel-primary); }
        .progress-steps { display: flex; gap: 8px; }
        .progress-steps > span {
            flex: 1; height: 6px; border-radius: 999px;
            background: color-mix(in srgb, var(--funnel-secondary) 25%, transparent);
        }
        .progress-steps > span.done { background: var(--funnel-primary); }
        h1 { font-size: 22px; margin: 20px 0 4px; }
        p.help { margin: 0 0 20px; color: color-mix(in srgb, var(--funnel-text) 65%, transparent); }
        .option {
            display: block; padding: 12px 14px; margin-bottom: 8px; cursor: default;
            border: 2px solid color-mix(in srgb, var(--funnel-secondary) 35%, transparent);
            border-radius: var(--funnel-radius); background: transparent;
        }
        .option.selected { border-color: var(--funnel-primary); background: color-mix(in srgb, var(--funnel-primary) 8%, transparent); }
        .actions { display: flex; gap: 8px; margin-top: 24px; }
        button { font: inherit; padding: 10px 18px; border-radius: var(--funnel-radius); border: 0; cursor: default; }
        .btn-primary { background: var(--funnel-primary); color: #fff; }
        .btn-secondary { background: transparent; color: var(--funnel-text); border: 2px solid color-mix(in srgb, var(--funnel-secondary) 45%, transparent); }
        .logo { max-height: 36px; margin-bottom: 16px; }
    </style>
</head>
<body>
    @if ($progressStyle === \App\Constants\FunnelProgressStyle::BAR)
        <div class="progress-bar"><span></span></div>
    @elseif ($progressStyle === \App\Constants\FunnelProgressStyle::STEPS)
        <div class="progress-steps">
            <span class="done"></span><span class="done"></span><span class="done"></span><span></span><span></span>
        </div>
    @endif

    <h1>{{ __('builder.theme.preview_question') }}</h1>
    <p class="help">{{ __('builder.theme.preview_help') }}</p>

    <div class="option selected">{{ __('builder.theme.preview_option_one') }}</div>
    <div class="option">{{ __('builder.theme.preview_option_two') }}</div>
    <div class="option">{{ __('builder.theme.preview_option_three') }}</div>

    <div class="actions">
        <button type="button" class="btn-secondary">{{ $backLabel }}</button>
        <button type="button" class="btn-primary">{{ $nextLabel }}</button>
        <button type="button" class="btn-primary">{{ $submitLabel }}</button>
    </div>
</body>
</html>
