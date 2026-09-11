{{--
    Der Knopf am Ende einer Leadzeile. Drei Faelle, drei Knoepfe:

      offen           "Anrufen"  -- Click-to-Call, die Nummer ist noch verdeckt
      erreicht        "Nummer"   -- ab jetzt darf gewaehlt werden
      nicht erreicht  "Details"  -- es gibt nichts mehr anzurufen

    Steht je Zeile zweimal im Markup (breite Spalte und gestapelte Reihe),
    deshalb hier. Ob die Rufnummer offenliegt, hat der LeadPresenter
    entschieden; diese Komponente liest nur, was er geliefert hat.
--}}
@props(['row'])

@php
    // Von aussen mitgegebene Klassen (etwa flex-1) gehoeren an den Knopf, nicht
    // an einen Rahmen darum: Sonst bleibt der Knopf schmal und der Rahmen waechst.
    $extra = (string) $attributes->get('class');
@endphp

@if ($row['open'])
    <button
        type="button"
        class="btn-primary min-h-10 px-4 {{ $extra }}"
        wire:click="startCall({{ $row['id'] }})"
        wire:loading.attr="disabled"
        @disabled(! $row['can_call'])
        @if ($row['call_hint'] !== null) title="{{ $row['call_hint'] }}" @endif
    >
        <x-app.icon name="phone" class="size-4 shrink-0" />
        {{ __('marketplace.purchased.call') }}
    </button>
@elseif ($row['billable'] && $row['phone_link'] !== null)
    <a href="{{ $row['phone_link'] }}" class="btn-secondary min-h-10 px-4 {{ $extra }}">
        <x-app.icon name="phone" class="size-4 shrink-0" />
        <span class="tabular-nums">{{ __('marketplace.purchased.number') }}</span>
    </a>
@else
    <a href="{{ $row['url'] }}" class="btn-ghost min-h-10 px-4 {{ $extra }}">
        {{ __('marketplace.purchased.details') }}
    </a>
@endif
