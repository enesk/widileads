{{--
    Ein Auswahlfeld mit dem Pfeil aus dem Entwurf (LP-PORTAL-003).

    Der Pfeil ist gezeichnet und nicht der des Browsers, deshalb liegt er als
    eigenes Symbol ueber dem Feld und nimmt keine Klicks an. Diese drei Zeilen
    wiederholen sich sonst auf jeder Seite mit einer Sortierung.
--}}
@props(['label', 'name', 'id' => null, 'srOnly' => true])

@php
    $id = $id ?? $name;
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'relative']) }}>
    <label for="{{ $id }}" class="{{ $srOnly ? 'sr-only' : 'mb-1.5 block text-sm font-medium text-zinc-700' }}">{{ $label }}</label>

    <select id="{{ $id }}" name="{{ $name }}" {{ $attributes->except('class')->merge(['class' => 'input appearance-none pr-10']) }}>
        {{ $slot }}
    </select>

    <x-app.icon name="chevron-down" class="size-5 absolute right-3 bottom-2.5 text-zinc-400 pointer-events-none" />
</div>
