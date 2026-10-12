{{--
    Wizard-Schritt 1: die Branchen (LP-ONBOARD-008).

    Steht in der Karte der Wizard-Huelle, die Ueberschrift und die Knoepfe
    gehoeren ihr. Hier stehen die Kacheln, die Live-Zeile und die
    Fehlermeldung.

    **Warum Alpine und nicht nur wire:model.** Eine Kachel muss im selben
    Moment gewaehlt aussehen, in dem sie angeklickt wird -- nicht nach einer
    Serverantwort. Die Auswahl liegt deshalb doppelt: in Alpine fuer das
    Aussehen und die Summe, und ueber $wire.$set im Zustand der Komponente,
    aber ohne eigene Anfrage (dritter Parameter false). Mitgeschickt wird sie
    mit der naechsten Anfrage -- und das ist genau der Klick auf "Weiter", der
    sie prueft und speichert.

    Die Kachel ist ein <button aria-pressed> und keine Checkbox mit Label: Der
    Zustand ist "gedrueckt/nicht gedrueckt", und der ganze Kasten ist die
    Klickflaeche.
--}}
<div>
    @if ($industries === [])
        {{-- Kein veroeffentlichtes Portal im Angebot. Der Schritt geht ohne
             Auswahl durch (siehe StepIndustries::save), damit niemand im
             Wizard festsitzt. --}}
        <x-app.empty-state
            icon="grid"
            :title="__('portal.onboarding.industries.empty.title')"
            :description="__('portal.onboarding.industries.empty.text')"
        />
    @else
        <div
            x-data="{
                selected: @js(array_values($selected)),
                weekly: @js($weekly),
                pattern: @js(__('portal.onboarding.industries.summary')),
                showAll: false,
                isSelected(id) {
                    return this.selected.includes(id);
                },
                toggle(id) {
                    this.selected = this.isSelected(id)
                        ? this.selected.filter((value) => value !== id)
                        : [...this.selected, id];

                    /* Dritter Parameter false: setzen, ohne eine Anfrage
                       auszuloesen. Livewire schickt den geaenderten Wert mit
                       der naechsten Anfrage dieser Komponente mit -- und das
                       ist der Aufruf von save() durch 'Weiter'. */
                    $wire.$set('selected', this.selected, false);
                },
                get total() {
                    return this.selected.reduce((sum, id) => sum + (this.weekly[id] ?? 0), 0);
                },
                get summary() {
                    return this.pattern.replace('{count}', this.total);
                },
            }"
        >
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                @foreach ($industries as $index => $industry)
                    @php($isSelected = in_array($industry['funnel_id'], $selected, true))

                    {{-- Der Anfangszustand steht im Markup und wird von Alpine
                         nur weitergefuehrt (Objektschreibweise bei :class).
                         Haengte er allein an Alpine, saehe eine vorausgewaehlte
                         Kachel bis zum ersten Skriptdurchlauf ungewaehlt aus --
                         und die Kacheln hinter "Alle Branchen anzeigen" waeren
                         kurz alle zu sehen. Ein x-cloak gaebe es im Portal
                         nicht, das CSS dafuer fehlt. --}}
                    <button
                        type="button"
                        x-on:click="toggle({{ $industry['funnel_id'] }})"
                        x-bind:aria-pressed="isSelected({{ $industry['funnel_id'] }})"
                        aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                        x-bind:class="{
                            'border-brand bg-brand-50': isSelected({{ $industry['funnel_id'] }}),
                            @if ($index >= $visibleTiles) 'hidden': ! showAll, @endif
                        }"
                        @class([
                            'card-interactive relative flex w-full min-w-0 min-h-[44px] flex-col items-start gap-2 p-2.5 text-left md:p-3',
                            'border-brand bg-brand-50' => $isSelected,
                            'hidden' => $index >= $visibleTiles,
                        ])
                    >
                        {{-- Der Haken oben rechts. Nur sichtbar, wenn gewaehlt --
                             aria-pressed am Knopf sagt dasselbe, deshalb ist er
                             fuer die Vorlesesoftware verborgen. --}}
                        <span
                            x-bind:class="{ 'hidden': ! isSelected({{ $industry['funnel_id'] }}) }"
                            @class([
                                'absolute right-2 top-2 grid size-5 place-items-center rounded-full bg-brand text-white',
                                'hidden' => ! $isSelected,
                            ])
                            aria-hidden="true"
                        >
                            <x-app.icon name="check" class="size-3" />
                        </span>

                        <x-app.icon :name="$industry['icon']" class="size-6 text-brand" />

                        {{-- Zwei Zeilen, danach abgeschnitten. Auf 360 Pixeln
                             ist eine Spalte rund 150 Pixel breit -- bei der
                             Schriftskala des Portals (125 Prozent) passt dort
                             keine Grundschrift: ein Wort wie
                             "Umzugsunternehmen" liefe sonst aus der Kachel.
                             Deshalb eine Stufe kleiner, ab 768 Pixeln wieder
                             normal. Der Umbruch im Wort bleibt als letzte
                             Rettung stehen. --}}
                        <span class="line-clamp-2 w-full break-words hyphens-auto text-sm font-semibold text-zinc-900 md:text-base">{{ $industry['name'] }}</span>

                        <span class="w-full text-xs text-zinc-500 md:text-sm">
                            @if ($industry['weekly'] > 0)
                                {{ __('portal.onboarding.industries.weekly', ['count' => $industry['weekly']]) }}
                            @else
                                {{ __('portal.onboarding.industries.fresh') }}
                            @endif
                        </span>
                    </button>
                @endforeach
            </div>

            @if (count($industries) > $visibleTiles)
                <p class="mt-3">
                    <button
                        type="button"
                        x-show="! showAll"
                        x-on:click="showAll = true"
                        class="text-sm text-brand underline"
                    >
                        {{ __('portal.onboarding.industries.show_all') }}
                    </button>
                </p>
            @endif

            {{-- Die Live-Zeile. Sie rechnet im Browser und nicht auf dem
                 Server: Sonst waere jede Kachel eine Anfrage, nur damit eine
                 Zahl daneben stimmt. --}}
            <div class="bg-brand-50 mt-5 flex items-center gap-3 rounded-xl p-3">
                {{-- Die Breite steht am Rahmen und nicht an der Zeichnung:
                     .illustration setzt width 100 Prozent aus einer
                     ungeschichteten Regel und schlaegt damit jedes size-*
                     (dieselbe Stelle, an der die Wizard-Huelle mit max-h
                     arbeitet). --}}
                <span class="w-12 shrink-0">
                    <x-ui.illustration name="industries" :decorative="true" />
                </span>
                {{-- Der Satz steht serverseitig ausgeschrieben im Markup und
                     wird von Alpine bei jeder Aenderung ersetzt -- sonst waere
                     die Zeile bis zum ersten Skriptdurchlauf leer. --}}
                <p class="min-w-0 text-sm text-zinc-700" x-text="summary">
                    {{ str_replace('{count}', (string) $initialTotal, __('portal.onboarding.industries.summary')) }}
                </p>
            </div>
        </div>
    @endif

    @error('selected')
        <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
