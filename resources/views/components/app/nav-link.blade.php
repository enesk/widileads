{{--
    Ein Punkt der Portalnavigation (Portal Phase 1).

    Punkte ohne Ziel sind kein Versehen: Der Entwurf zeigt Seiten, die es noch
    nicht gibt. Sie bleiben an ihrem Platz, nehmen aber keine Klicks an und
    sagen per title und aria-disabled, warum.
--}}
@props(['label', 'icon', 'url' => null, 'active' => false])

@php
    $base = 'flex items-center gap-3 min-h-11 px-3 rounded-xl font-medium';
@endphp

@if ($url === null)
    <span class="{{ $base }} text-zinc-400 cursor-default" aria-disabled="true" title="{{ __('portal.nav.soon') }}">
        <x-app.icon :name="$icon" />{{ $label }}
    </span>
@else
    <a href="{{ $url }}"
       @class([$base, 'bg-brand-50 text-brand' => $active, 'text-zinc-700 hover:bg-zinc-100' => ! $active])
       @if ($active) aria-current="page" @endif>
        <x-app.icon :name="$icon" />{{ $label }}
    </a>
@endif
