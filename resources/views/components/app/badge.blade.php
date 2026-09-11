{{--
    Die Pille an der Karte: "Neu", ein Zaehler, ein Guthabenstand
    (LP-PORTAL-003).

    variant: neutral (grau) | brand (helle Markenfarbe) | solid (volle
    Markenfarbe, wie das "Neu" im Entwurf)
--}}
@props(['variant' => 'neutral'])

@php
    $classes = match ($variant) {
        'solid' => 'pill-solid',
        'brand' => 'pill-brand',
        default => 'pill',
    };
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
