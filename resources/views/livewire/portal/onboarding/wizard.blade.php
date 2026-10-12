{{--
    Die Huelle des Einrichtungs-Wizards (LP-ONBOARD-007).

    Aufbau nach dem Entwurf: oben das Markenband, darunter die Schritt-Leiste,
    ab 768 Pixeln zweispaltig -- links die Illustration des Schritts in einer
    Markenflaeche, rechts die Karte mit dem Schritt. Auf dem Telefon liegt die
    Illustration flach darueber und die beiden Knoepfe stehen in der
    festgehefteten Leiste am unteren Rand (x-app.bottom-bar -- ein Aufruf, der
    sich ab 768 Pixeln in die Karte zurueckzieht).

    Was in der Karte steht, gehoert der Schritt-Komponente
    (LP-ONBOARD-008/009/010). Hier steht kein Feld und keine Regel -- nur der
    Platz dafuer.
--}}
<div>
    {{-- Das Markenband. Eine ruhige Flaeche in der Marke oben, damit die
         Einrichtung nach Produkt aussieht und nicht nach Formular. Volle
         Markenflaechen sind die ausgewiesene widileads-Abweichung vom
         Skill. --}}
    <div class="bg-brand h-2" aria-hidden="true"></div>

    <div class="container-portal py-8 md:py-12">
        <div class="mx-auto max-w-5xl">
            <x-ui.step-rail
                :current="$stepSlug"
                :completed="$completedSlugs"
                :counter="$counter"
                class="mx-auto max-w-2xl"
            />

            <div class="mt-8 md:mt-12 grid gap-6 md:grid-cols-[18rem_1fr] md:gap-10 md:items-start md:max-w-4xl md:mx-auto">
                {{-- Die Illustration des Schritts. Sie sagt dasselbe wie die
                     Ueberschrift daneben, ist also Dekoration und bleibt vor der
                     Vorlesesoftware verborgen. --}}
                <div class="bg-brand-50 rounded-2xl p-5 md:p-6 flex items-center justify-center">
                    <x-ui.illustration
                        :name="$stepSlug"
                        :decorative="true"
                        class="max-h-36 md:max-h-none w-auto md:w-full"
                    />
                </div>

                <div class="w-full wizard-step" data-wizard-step="{{ $stepSlug }}">
                    <x-app.card class="p-5 md:p-6">
                        <p class="text-sm font-medium text-zinc-500">{{ $counter }}</p>

                        {{-- Der Fokus springt nach dem Schrittwechsel hierher
                             (wizard.js) -- deshalb tabindex="-1": anspringbar,
                             aber nicht in der Tabreihenfolge. --}}
                        <h1
                            id="wizard-heading"
                            tabindex="-1"
                            class="wizard-step__heading mt-1 text-2xl md:text-3xl font-bold tracking-tight text-zinc-900"
                        >
                            {{ __('portal.onboarding.steps.'.$stepSlug.'.heading') }}
                        </h1>

                        <p class="mt-3 text-zinc-600">
                            {{ __('portal.onboarding.steps.'.$stepSlug.'.text') }}
                        </p>

                        <div class="mt-6">
                            @if ($stepComponent)
                                @livewire($stepComponent, ['tenantUuid' => $tenantUuid])
                            @else
                                {{-- Solange der Schritt noch nicht gebaut ist:
                                     Die Huelle bleibt durchlaufbar, "Weiter" hakt
                                     den Schritt selbst ab. --}}
                                <p class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 px-4 py-6 text-sm text-zinc-500">
                                    {{ __('portal.onboarding.wizard.step_pending') }}
                                </p>
                            @endif
                        </div>

                        <x-app.bottom-bar :divider="true" class="md:mt-8">
                            @if ($canSkip)
                                <x-slot:note>
                                    <button type="button" class="underline hover:text-zinc-900" wire:click="skip">
                                        {{ __('portal.onboarding.wizard.skip') }}
                                    </button>
                                </x-slot:note>
                            @endif

                            @if ($canGoBack)
                                <x-slot:secondary>
                                    <button type="button" class="btn-ghost" wire:click="back">
                                        {{ __('portal.onboarding.wizard.back') }}
                                    </button>
                                </x-slot:secondary>
                            @endif

                            <x-slot:primary>
                                <button type="button" class="btn-primary" wire:click="submitStep" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="submitStep">
                                        {{ $isLastStep ? __('portal.onboarding.wizard.finish') : __('portal.onboarding.wizard.next') }}
                                    </span>
                                    <span wire:loading wire:target="submitStep">{{ __('portal.onboarding.wizard.working') }}</span>
                                </button>
                            </x-slot:primary>
                        </x-app.bottom-bar>
                    </x-app.card>

                </div>
            </div>
        </div>
    </div>

</div>
