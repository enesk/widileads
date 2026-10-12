{{--
    Die Schritt-Leiste des Onboardings (LP-ONBOARD-007).

    <x-ui.step-rail current="region" :completed="['industries']" counter="Schritt 2 von 3" />

    Vier Symbole in einer Reihe statt eines Fortschrittsbalkens: Rufnummer,
    Branchen, Region, Zahlungsweise. Ein Balken sagt "60 %", die Leiste sagt
    "die Nummer steht, die Branchen stehen, jetzt die Region" -- und sie zeigt
    zugleich, dass es nach diesem Schritt nur noch einen gibt. Der Skill
    verbietet Kreise mit Haken; die Abweichung steht im Ticket und gilt nur
    hier, nicht im Portal daneben.

    Die Rufnummer ist immer erledigt: Ohne sie kommt niemand an diese Seite
    (EnsurePhoneVerified). Sie steht trotzdem in der Leiste, weil sie der erste
    Schritt war, den der Kaeufer gemacht hat -- eine Leiste, die bei zwei
    anfaengt, sieht aus wie ein Fehler.

    Zustaende am data-state des Eintrags, Form und Linien in
    resources/css/components/step-rail.css. Auf schmalen Bildschirmen tragen
    nur der aktive Schritt und die Zaehlzeile Text; vier Beschriftungen
    nebeneinander passen bei 125 % Grundschrift auf kein Telefon.

    Erwartet: current (Adressstueck des aktiven Schritts), completed (Liste
    erledigter Adressstuecke), counter ("Schritt 2 von 3").
--}}
@props([
    'current',
    'completed' => [],
    'counter' => null,
])

@php
    /* Reihenfolge und Symbole stehen hier, die Beschriftungen in der
       Sprachdatei. Die Rufnummer ist kein Wizard-Schritt und hat deshalb kein
       Adressstueck -- sie ist der feste erste Eintrag. */
    $items = [
        ['key' => 'phone', 'icon' => 'phone', 'state' => 'done'],
        ['key' => 'industries', 'icon' => 'grid', 'state' => null],
        ['key' => 'region', 'icon' => 'pin', 'state' => null],
        ['key' => 'payment', 'icon' => 'wallet', 'state' => null],
    ];

    $items = array_map(function (array $item) use ($current, $completed): array {
        $item['state'] ??= match (true) {
            $item['key'] === $current => 'active',
            in_array($item['key'], $completed, true) => 'done',
            default => 'open',
        };

        $item['label'] = __('portal.onboarding.steps.'.$item['key'].'.label');

        return $item;
    }, $items);
@endphp

<div {{ $attributes->class(['step-rail-block']) }}>
    <ol class="step-rail" aria-label="{{ __('portal.onboarding.wizard.rail_label') }}">
        @foreach ($items as $item)
            <li class="step-rail__item" data-state="{{ $item['state'] }}">
                <span class="step-rail__dot">
                    @if ($item['state'] === 'done')
                        <x-app.icon name="check" class="size-5 shrink-0" />
                    @else
                        <x-app.icon :name="$item['icon']" class="size-5 shrink-0" />
                    @endif
                </span>

                <span class="step-rail__label">{{ $item['label'] }}</span>

                {{-- Der Zustand gehoert auch in die Vorlesesoftware; die Farbe
                     allein sagt dort nichts. --}}
                <span class="sr-only">{{ __('portal.onboarding.wizard.state.'.$item['state']) }}</span>
            </li>
        @endforeach
    </ol>

    @if ($counter)
        <p class="step-rail__counter">{{ $counter }}</p>
    @endif
</div>
