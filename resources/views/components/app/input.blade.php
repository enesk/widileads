{{--
    Ein beschriftetes Eingabefeld (LP-PORTAL-003).

    Die Beschriftung gehoert zum Feld, auch wenn sie im Entwurf unsichtbar ist:
    ohne sie liest ein Screenreader nur "Eingabefeld". Darum sitzt sie hier fest
    am Feld und wird mit srOnly hoechstens versteckt, nie weggelassen.
--}}
@props(['label', 'name', 'id' => null, 'type' => 'text', 'srOnly' => false, 'hint' => null, 'error' => null])

@php
    $id = $id ?? $name;
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'flex flex-col gap-1.5']) }}>
    <label for="{{ $id }}" class="{{ $srOnly ? 'sr-only' : 'text-sm font-medium text-zinc-700' }}">{{ $label }}</label>

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except('class')->merge(['class' => 'input']) }}
    >

    @if ($hint && ! $error)
        <p class="text-sm text-zinc-500">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" class="text-sm text-red-600">{{ $error }}</p>
    @endif
</div>
