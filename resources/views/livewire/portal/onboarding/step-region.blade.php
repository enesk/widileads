{{--
    Wizard-Schritt 2: die Region (LP-ONBOARD-009).

    Steht in der Karte der Wizard-Huelle; Ueberschrift, Illustration und der
    Knopf "Weiter" gehoeren ihr. Hier stehen die beiden Auswahlkarten, die
    Deutschland-Karte, die Regionsfelder und die Live-Zeile.

    **Warum hier wire:model.live und nicht Alpine wie in Schritt 1 und 3.**
    Dort haengt am Klick nur das Aussehen der Kachel -- das darf der Browser
    allein. Hier haengt an jeder Aenderung eine Zahl, die aus den Leads der
    letzten vier Wochen kommt: Ortsname, Anfragezahl, Hinweis. Die kennt der
    Browser nicht, also fragt er. Was trotzdem ohne Serverrunde laeuft, ist
    alles, was mit dem Finger am Regler haengt -- Zahl, gefuellte Schiene und
    die Groesse des Kreises (resources/js/modules/germany-map.js).

    Die Auswahlkarte ist ein <button aria-pressed> wie in Schritt 3: Der ganze
    Kasten ist die Klickflaeche, und der Zustand ist "gedrueckt".

    Erwartete Daten:
      $isRadius                 ist "Nur in meiner Region" gewaehlt?
      $steps                    die Stufen des Reglers in Kilometern
      $radius                   der gewaehlte Umkreis in Kilometern
      $place                    Ortsname zur PLZ, oder null
      $isUnknownPlz             eingegeben, aber nicht gefunden
      $weekly                   Anfragen je Woche im Gebiet
      $mapX/$mapY/$mapRadius    Pin und Kreis in viewBox-Einheiten
      $showArea                 Umkreis sichtbar?
      $projectionScale, $projectionKmPerDegree  fuer germany-map.js
--}}
<div>
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach ([
            \App\Constants\BuyerRegion::NATIONWIDE->value => 'nationwide',
            \App\Constants\BuyerRegion::RADIUS->value => 'radius',
        ] as $value => $key)
            @php($isChosen = $region === $value)

            <button
                type="button"
                wire:click="$set('region', '{{ $value }}')"
                aria-pressed="{{ $isChosen ? 'true' : 'false' }}"
                @class([
                    'card-interactive relative flex min-h-[44px] flex-col items-start gap-1 p-4 text-left',
                    'border-brand bg-brand-50' => $isChosen,
                ])
            >
                @if ($key === 'nationwide')
                    {{-- Die Empfehlung steht als Pille da und nicht nur im
                         Text: Deutschlandweit ist die Vorgabe, und wer
                         einschraenkt, soll wissen, dass er von ihr
                         abweicht. --}}
                    <span class="pill-solid absolute right-3 top-3">{{ __('portal.onboarding.region.recommended') }}</span>
                @endif

                <x-app.icon :name="$key === 'nationwide' ? 'grid' : 'pin'" class="size-6 text-brand" />

                <span class="mt-1 font-semibold text-zinc-900">
                    {{ __('portal.onboarding.region.'.$key.'.title') }}
                </span>

                <span class="text-sm text-zinc-500">
                    {{ __('portal.onboarding.region.'.$key.'.text') }}
                </span>
            </button>
        @endforeach
    </div>

    {{-- Die Regionsfelder. Nur bei "Region" im Markup -- ein verstecktes
         Formularfeld wuerde die Tabreihenfolge mitnehmen und von der
         Vorlesesoftware weiter angesagt. --}}
    @if ($isRadius)
        <div class="region-fields mt-5 grid gap-5 sm:grid-cols-2 sm:items-start">
            <x-app.input
                name="postalCode"
                id="region-postal-code"
                wire:model.live.debounce.300ms="postalCode"
                :label="__('portal.onboarding.region.plz.label')"
                inputmode="numeric"
                autocomplete="postal-code"
                maxlength="5"
                pattern="[0-9]*"
                placeholder="{{ __('portal.onboarding.region.plz.placeholder') }}"
                :error="$errors->first('postalCode') ?: ($isUnknownPlz ? __('portal.onboarding.region.errors.unknown_plz') : null)"
            />

            {{-- Der Ortsname steht neben dem Feld und nicht in der Karte: Er
                 ist die Antwort auf "habe ich die richtige PLZ getippt?", und
                 fuer die Vorlesesoftware ist er zusammen mit der Zahl unten
                 die ganze Auskunft, die die Karte bildlich gibt. --}}
            <p class="text-sm text-zinc-700 sm:mt-7" aria-live="polite">
                @if ($place)
                    <span class="font-medium text-zinc-900">{{ $place }}</span>
                @endif
            </p>

            <div class="sm:col-span-2">
                <div class="flex items-end justify-between gap-3">
                    <label for="region-radius" class="text-sm font-medium text-zinc-700">
                        {{ __('portal.onboarding.region.radius_field.label') }}
                    </label>

                    {{-- Die grosse Zahl. germany-map.js schreibt sie beim
                         Ziehen, der Server beim naechsten Zeichnen -- beide
                         dieselbe Zahl. --}}
                    <p class="text-2xl font-bold tabular-nums text-zinc-900">
                        <span id="region-radius-value">{{ $radius }}</span>
                        <span class="text-base font-medium text-zinc-500">{{ __('portal.onboarding.region.radius_field.unit') }}</span>
                    </p>
                </div>

                {{-- Ein nativer Regler: Tastatur, Finger und Vorlesesoftware
                     bringt der Browser mit. Die Stufen stehen als Liste
                     darunter; list/datalist waere die elegantere Fassung,
                     wird aber von Safari bis heute nicht gezeichnet. --}}
                <input
                    type="range"
                    id="region-radius"
                    class="range-portal mt-2"
                    wire:model.live.debounce.300ms="radiusIndex"
                    data-radius-slider="region-radius-value"
                    data-radius-steps="{{ json_encode($steps) }}"
                    min="0"
                    max="{{ count($steps) - 1 }}"
                    step="1"
                    {{-- Der gefuellte Teil der Schiene steht schon im Markup:
                         Haengte er allein an germany-map.js, waere die Schiene
                         bis zum ersten Skriptdurchlauf leer, obwohl der Daumen
                         in der Mitte steht. --}}
                    style="--range-progress: {{ count($steps) > 1 ? round($radiusIndex / (count($steps) - 1) * 100) : 0 }}%"
                    aria-valuetext="{{ $radius }} {{ __('portal.onboarding.region.radius_field.unit') }}"
                >

                <p class="range-ticks mt-1 text-xs text-zinc-500" style="--range-ticks: {{ count($steps) }}" aria-hidden="true">
                    @foreach ($steps as $step)
                        <span>{{ $step }}</span>
                    @endforeach
                </p>
            </div>
        </div>
    @endif

    {{-- Die Karte. Sie zeichnet, was die Zeile darunter in Worten sagt, und
         ist deshalb fuer die Vorlesesoftware nicht da. --}}
    <div class="mt-5 flex justify-center">
        <svg
            viewBox="0 0 {{ (int) \App\Support\Geo\SimpleProjection::WIDTH }} {{ (int) \App\Support\Geo\SimpleProjection::HEIGHT }}"
            class="germany-map"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            focusable="false"
            data-germany-map-canvas
            data-state="{{ $isRadius ? 'radius' : 'nationwide' }}"
            data-projection-scale="{{ $projectionScale }}"
            data-projection-km-per-degree="{{ $projectionKmPerDegree }}"
        >
            @include('partials.maps.germany')
        </svg>
    </div>

    {{-- Die Live-Zeile. Bei 0 ist sie kein Fehler, sondern ein Rat: Der
         Kaeufer hat nichts falsch gemacht, sein Umkreis ist nur klein. --}}
    <div
        @class([
            'mt-5 flex items-start gap-3 rounded-xl p-3 text-sm',
            'bg-brand-50 text-zinc-700' => $weekly > 0,
            'border border-amber-200 bg-amber-50 text-amber-900' => $weekly === 0,
        ])
        aria-live="polite"
    >
        <x-app.icon :name="$weekly > 0 ? 'trend-up' : 'alert'" class="mt-0.5 size-5 shrink-0" />

        <p class="min-w-0">
            @if ($weekly > 0)
                {{ __('portal.onboarding.region.summary', ['count' => $weekly]) }}
            @else
                {{ __('portal.onboarding.region.empty') }}
            @endif
        </p>
    </div>
</div>
