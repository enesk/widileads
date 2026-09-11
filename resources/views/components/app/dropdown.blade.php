{{--
    Ein Aufklappmenue des Portals (Ticket #6).

    Zwei Stellen brauchen dasselbe: der Workspace-Wechsler ueber der Navigation
    und der Kontoknopf im Kopf. Das Auf und Zu steht in resources/js/portal.js
    und haengt an data-dropdown-toggle -- im Markup steht kein onclick.

    Die Kennung wird hier erzeugt und nicht von aussen gesetzt: Die Navigation
    steht zweimal auf der Seite (Schublade und feste Spalte), zwei feste
    Kennungen waeren doppelt und das Menue liesse sich nur einmal oeffnen.

    Erwartete Daten: $trigger (Inhalt des Knopfes), $slot (Inhalt des Menues).
    Die Klassen des Knopfes kommen als Attribute am Slot:
    <x-slot:trigger class="...">. Optional: $align ('left' oder 'right'),
    $width (Breitenklasse des Menues), $label (aria-label des Knopfes).
--}}
@props(['align' => 'left', 'width' => 'w-64', 'label' => null])

@php
    $menuId = 'portal-menu-'.\Illuminate\Support\Str::random(8);
@endphp

<div class="relative">
    <button
        type="button"
        data-dropdown-toggle="{{ $menuId }}"
        aria-haspopup="true"
        aria-expanded="false"
        aria-controls="{{ $menuId }}"
        @if ($label) aria-label="{{ $label }}" @endif
        {{ $trigger->attributes }}
    >{{ $trigger }}</button>

    <div
        id="{{ $menuId }}"
        role="menu"
        @class([
            'hidden absolute z-50 mt-2 max-h-80 overflow-y-auto rounded-xl border border-zinc-200 bg-white p-1 shadow-lg',
            $width,
            'right-0' => $align === 'right',
            'left-0' => $align !== 'right',
        ])
    >{{ $slot }}</div>
</div>
