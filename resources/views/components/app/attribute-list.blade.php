{{--
    Die Merkmale eines Leads: Frage und Antwort.

    Vorher stand das als zweispaltiges Raster mit `grid-cols-[auto_1fr]`. Die
    Beschriftungsspalte richtet sich dort nach der LAENGSTEN Beschriftung -- und
    die Beschriftungen sind Fragen aus dem Funnel, nicht Feldnamen. Eine Frage
    wie "Wie viele Elektroinstallationen sollen erneuert werden?" nahm die
    ganze Breite, und jede Antwort darunter stand in einem schmalen Streifen am
    rechten Rand, Wort fuer Wort umgebrochen.

    Deshalb steht jedes Paar jetzt als kleiner Block: die Frage klein und grau
    oben, die Antwort darunter. Das haelt jede Laenge aus, auf beiden Seiten,
    und kein Paar nimmt einem anderen den Platz weg.

    Mit `columns=2` stehen die Bloecke zu zweit nebeneinander -- aber nach der
    Breite der KARTE, nicht des Bildschirms (Container-Query `@md`). Auf der
    Detailseite sitzt die Karte am Desktop in einer schmalen Spalte von rund
    370 Pixeln; eine Bildschirmregel haette dort zwei Spalten zu je 140 Pixeln
    erzwungen, und eine lange Frage stuende wieder Wort fuer Wort.

    items:   entweder ['Frage' => 'Antwort', ...]
             oder     [['label' => 'Frage', 'value' => 'Antwort'], ...]
    columns: 1 oder 2
--}}
@props(['items' => [], 'columns' => 1])

@php
    $pairs = [];

    foreach ($items as $key => $item) {
        if (is_array($item)) {
            $pairs[] = ['label' => (string) ($item['label'] ?? ''), 'value' => (string) ($item['value'] ?? '')];
        } else {
            $pairs[] = ['label' => (string) $key, 'value' => (string) $item];
        }
    }
@endphp

<div {{ $attributes->merge(['class' => '@container']) }}>
<dl @class(['grid gap-x-6 gap-y-3 text-sm', '@md:grid-cols-2' => (int) $columns === 2])>
    @foreach ($pairs as $pair)
        {{-- min-w-0 und break-words: Ohne beides schiebt ein einzelnes langes
             Wort (eine URL, ein zusammengesetztes Substantiv) das ganze
             Raster ueber den Rand. --}}
        <div class="min-w-0">
            <dt class="text-xs text-zinc-500 break-words">{{ $pair['label'] }}</dt>
            <dd class="mt-0.5 text-zinc-900 font-medium break-words">{{ $pair['value'] }}</dd>
        </div>
    @endforeach
</dl>
</div>
