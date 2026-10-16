{{--
    Der Knopf am Ende einer Leadzeile. Drei Faelle, drei Knoepfe:

      Nummer frei     "Nummer"   -- ab jetzt waehlt der Kaeufer selbst
      offen, verdeckt "Anrufen"  -- Click-to-Call ueber die Bridge
      sonst           "Details"  -- es gibt nichts mehr anzurufen

    Die Freigabe entscheidet, nicht der Abrechnungsstand (Ticket #6): Seit der
    neuen Regel kommt die Nummer auch bei einem noch offenen Lead heraus --
    nach dem dritten gezaehlten Anruf. Stuende hier weiter "offen zuerst",
    boete die Zeile dort den Bridge-Anruf an, waehrend die Detailseite
    daneben die Nummer zum Waehlen zeigt.

    "Nummer frei" heisst dabei: endgueltig entschieden, also abgerechnet oder
    Gespraech angenommen (Ticket #100). Bei der Freigabe auf Zeit -- genug
    gezaehlte Versuche, Lead offen -- liefert die Seite keinen `phone_link`,
    und es bleibt beim Anruf ueber die Bridge: Nur der zaehlt.

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

@if ($row['phone_link'] !== null)
    <a href="{{ $row['phone_link'] }}" class="btn-secondary min-h-10 px-4 {{ $extra }}">
        <x-app.icon name="phone" class="size-4 shrink-0" />
        <span class="tabular-nums">{{ __('marketplace.purchased.number') }}</span>
    </a>
@elseif ($row['open'])
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
@else
    <a href="{{ $row['url'] }}" class="btn-ghost min-h-10 px-4 {{ $extra }}">
        {{ __('marketplace.purchased.details') }}
    </a>
@endif
