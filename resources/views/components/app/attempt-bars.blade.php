{{--
    Der Versuchsfortschritt als drei kurze Balken plus "2/3".

    Steht in jeder Leadzeile zweimal im Markup -- einmal in der breiten Spalte,
    einmal in der gestapelten Reihe fuer schmale Bildschirme. Deshalb hier und
    nicht zweimal ausgeschrieben.

    Die Zahl gehoert dazu: Drei Balken allein sind fuer einen Screenreader
    nichts, und auch mit den Augen zaehlt man sie sonst jedes Mal nach.
--}}
@props(['done' => 0, 'total' => 3])

@php
    $done = max(0, min((int) $done, (int) $total));
    $label = __('marketplace.purchased.attempts_label', ['done' => $done, 'total' => $total]);
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-1.5']) }} title="{{ $label }}" aria-label="{{ $label }}">
    @for ($index = 0; $index < (int) $total; $index++)
        <span @class(['h-1.5 w-5 rounded-full', 'bg-brand' => $index < $done, 'bg-zinc-200' => $index >= $done])></span>
    @endfor
    <span class="text-xs text-zinc-500 ml-1 tabular-nums" aria-hidden="true">{{ $done }}/{{ $total }}</span>
</div>
