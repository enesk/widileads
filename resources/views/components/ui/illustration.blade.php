{{--
    Die Illustrationen des Onboardings (LP-ONBOARD-006).

    <x-ui.illustration name="phone" class="max-w-xs" />
    <x-ui.illustration name="call" state="answered" :decorative="true" />

    Der Rahmen steht hier, die Motive liegen als Partials unter
    resources/views/partials/illustrations/. So tragen sie garantiert alle
    dieselbe viewBox und dieselbe Beschriftungsregel, und ein neues Motiv ist
    eine Datei, kein zweites Stueck Geruest.

    viewBox steht absichtlich vor der Attributliste: Bei doppeltem Attribut
    gilt im HTML das erste, damit laesst sich die Zeichenflaeche von aussen
    nicht verbiegen. Groesse kommt ueber class (width: 100% ist die Vorgabe).

    Beschriftung: Ein Bild, das neben einer Ueberschrift dasselbe sagt, ist
    Dekoration und wird vor der Vorlesesoftware versteckt (decorative). Traegt
    es die Aussage selbst -- etwa die gesperrte Rufnummer --, bekommt es ein
    aria-label. Die Vorgabetexte liegen unter portal.illustrations.<name>,
    label ueberschreibt sie, decorative unterdrueckt sie ganz.
--}}
@props([
    'name',
    'decorative' => false,
    'label' => null,
    'state' => null,
])

@php
    $partial = 'partials.illustrations.'.$name;

    /* Ohne Angabe klingelt es: Das ist der Ausgangszustand der Abrechnungs-
       Erklaerung, und ein Hoerer ohne jeden Zustand haette keine Wellen. */
    $state = $name === 'call' ? ($state ?? 'ringing') : $state;

    /* Die Vorgabetexte stehen als Schluessel unter portal.illustrations.<name>.
       Fehlt der Schluessel, wird das Bild dekorativ: Ein vorgelesener
       Schluesselname waere schlimmer als gar keine Beschriftung. */
    $resolvedLabel = $label;

    if ($resolvedLabel === null && ! $decorative) {
        $labelKey = 'portal.illustrations.'.$name;
        $resolvedLabel = \Illuminate\Support\Facades\Lang::has($labelKey) ? __($labelKey) : null;
    }

    $decorative = $decorative || $resolvedLabel === null;

    /* Der Name wird eingeschraenkt, bevor er in @include landet -- ein Punkt
       darin waere ein Pfad in eine andere View. */
    $exists = preg_match('/^[a-z0-9-]+$/', (string) $name) === 1 && view()->exists($partial);

    if (! $exists && app()->environment('local', 'testing')) {
        throw new InvalidArgumentException(
            "Illustration [{$name}] gibt es nicht. Erwartet wird resources/views/partials/illustrations/{$name}.blade.php"
        );
    }
@endphp

@if ($exists)
    <svg viewBox="0 0 320 240" {{ $attributes->class(['illustration']) }}
         @if ($state) data-state="{{ $state }}" @endif
         @if ($decorative) aria-hidden="true" focusable="false"
         @else role="img" aria-label="{{ $resolvedLabel }}" @endif
    >
        @include($partial)
    </svg>
@endif
