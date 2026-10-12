{{--
    Die Aktionsleiste eines Screens (LP-ONBOARD-019).

    <x-app.bottom-bar :divider="true">
        <x-slot:note>
            <button type="button" class="underline" wire:click="skip">Spaeter einrichten</button>
        </x-slot:note>

        <x-slot:secondary>
            <button type="button" class="btn-ghost" wire:click="back">Zurueck</button>
        </x-slot:secondary>

        <x-slot:primary>
            <button type="button" class="btn-primary" wire:click="submitStep">Weiter</button>
        </x-slot:primary>
    </x-app.bottom-bar>

    Ein Aufruf, zwei Erscheinungen: Auf dem Telefon heftet sich die Leiste an
    den unteren Rand, ab 768 Pixeln stehen dieselben Knoepfe rechtsbuendig im
    Fluss -- also dort, wo die Komponente aufgerufen wird, in der Regel am Ende
    der Karte. Deshalb steht die Knopfreihe nur einmal im Markup und nicht
    zweimal (einmal davon versteckt), wie es die Screens vorher gebaut haben.

    Der Platz, den die fixierte Leiste dem Seiteninhalt wegnimmt, gibt die
    Komponente selbst zurueck (Abstand am Seitenende ueber :has in
    resources/css/components/bottom-bar.css). Der Screen braucht dafuer kein
    pb-28 und darf auch keines setzen -- sonst steht der Abstand zweimal.

    Fixiert ist immer nur ein Element: Wo diese Leiste steht, scrollt die
    Kopfzeile mit (x-layouts.portal-focus ist genau dafuer gebaut). In
    x-layouts.portal-app klebt die Kopfzeile oben -- dort gehoeren die Knoepfe
    in die Karte, nicht in diese Leiste.

    Erwartet: primary (Pflicht, der Hauptknopf), secondary (optional, der
    ruhige Knopf links -- als btn-ghost), note (optional, ein Satz darueber).
    divider zieht ab md eine Trennlinie darueber, sinnvoll am Ende einer Karte.
    inline nimmt die Fixierung ganz heraus (auch auf dem Telefon) -- fuer
    Knoepfe, die zu einem Abschnitt gehoeren und nicht zum ganzen Screen.
--}}
@props(['divider' => false, 'inline' => false])

@php
    /* Nicht uebergebene Slots stehen in einer anonymen Komponente gar nicht
       als Variable zur Verfuegung -- deshalb isset und nicht isEmpty allein. */
    $hasPrimary = isset($primary) && ! $primary->isEmpty();
    $hasNote = isset($note) && ! $note->isEmpty();
    $hasSecondary = isset($secondary) && ! $secondary->isEmpty();

    if (! $hasPrimary) {
        throw new InvalidArgumentException('x-app.bottom-bar braucht den Slot "primary".');
    }
@endphp

<div
    {{ $attributes->class([
        'bottom-bar',
        'bottom-bar--with-note' => $hasNote,
        'bottom-bar--divider' => $divider,
        'bottom-bar--inline' => $inline,
    ]) }}
>
    @if ($hasNote)
        <p class="bottom-bar__note">{{ $note }}</p>
    @endif

    <div class="bottom-bar__actions">
        @if ($hasSecondary)
            <div class="bottom-bar__secondary">{{ $secondary }}</div>
        @endif

        <div class="bottom-bar__primary">{{ $primary }}</div>
    </div>
</div>
