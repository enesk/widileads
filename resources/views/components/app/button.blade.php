{{--
    Der Knopf des Portals (LP-PORTAL-003).

    Ein Ziel macht daraus einen Verweis, sonst bleibt es ein <button>. Beide
    sehen gleich aus -- im Entwurf stehen "Lead kaufen" als Knopf und "Aufladen"
    als Verweis direkt nebeneinander.

    variant: primary | secondary | ghost
--}}
@props(['variant' => 'primary', 'href' => null, 'type' => 'button', 'icon' => null])

@php
    $classes = 'btn-'.$variant;
@endphp

@if ($href === null)
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-app.icon :name="$icon" />@endif
        {{ $slot }}
    </button>
@else
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-app.icon :name="$icon" />@endif
        {{ $slot }}
    </a>
@endif
