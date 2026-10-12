{{--
    Der 3-Schritte-Player "So wird abgerechnet" (LP-ONBOARD-011).

    Steht an drei Stellen: auf dem Abschluss-Screen, im Dialog von
    Wizard-Schritt 3 und auf der Seite "Rufnummer bestaetigen". Deshalb bringt
    er keinen eigenen Rahmen und keine Aussenabstaende mit -- nur den Inhalt.

    **Alle drei Schritte stehen vollstaendig im HTML** und werden nur ein- und
    ausgeblendet. Ein Schritt, der erst von einem Skript geschrieben wird,
    waere ohne Skript ein leerer Kasten; so bleibt er lesbar, auch wenn Alpine
    nicht laeuft (dann steht der erste Schritt da, und die Punkte tun nichts).

    Bewegung ist Zugabe: Ohne sie unterscheiden sich die drei Zustaende am Bild
    (klingelnder, angenommener, durchgestrichener Hoerer), am Haken und am
    Betrag -- nicht an einer Animation.

    Erwartete Daten:
      $steps     state, title, text, charge (Betrag mit Vorzeichen oder null)
      $animation from, to (Cent), currency -- die herunterlaufende Guthabenzahl
      $autoplay  laeuft die Vorfuehrung von selbst los?
--}}
<div
    class="billing-explainer"
    x-data="billingExplainer({ count: {{ count($steps) }}, autoplay: @js($autoplay), animation: @js($animation) })"
>
    <p class="text-sm font-semibold text-zinc-900">
        {{ __('portal.onboarding.done.explainer.heading') }}
    </p>

    {{-- Die Buehne. Hoehe wird nicht festgenagelt: Der laengste Satz ist auf
         360 Pixeln vier Zeilen lang, ein fester Kasten wuerde ihn abschneiden.
         Damit die Schritte beim Wechsel nicht springen, liegen sie im selben
         Raster uebereinander (grid, alle in Zeile 1) -- der hoechste bestimmt
         die Hoehe. --}}
    <div class="billing-explainer__stage mt-4">
        @foreach ($steps as $index => $step)
            <div
                class="billing-explainer__step"
                x-show="step === {{ $index }}"
                x-bind:aria-hidden="step === {{ $index }} ? 'false' : 'true'"
                @if ($index > 0) style="display: none" @endif
                @if ($index > 0) aria-hidden="true" @endif
                wire:key="explainer-step-{{ $index }}"
            >
                {{-- Das Motiv traegt die Aussage nicht allein -- die steht als
                     Ueberschrift daneben -- und bleibt deshalb Dekoration.
                     Die Breite kommt vom Rahmen und nicht von einer
                     size-Utility: .illustration setzt width 100%. --}}
                <div class="billing-explainer__art">
                    <x-ui.illustration
                        name="call"
                        :state="$step['state']"
                        :decorative="true"
                        class="max-h-36"
                    />
                </div>

                <div class="min-w-0">
                    <p class="flex items-start gap-2 font-semibold text-zinc-900">
                        {{-- Der Haken des angenommenen Anrufs ist die eine
                             Stelle, an der gruen steht: Das Motiv selbst
                             bleibt bei der Markenfarbe (siehe
                             components/illustrations.css), hier nebenan sagt
                             der Haken "das hat gezaehlt". --}}
                        @if ($step['state'] === 'answered')
                            <x-app.icon name="check" class="mt-1 size-4 shrink-0 text-emerald-600" />
                        @elseif ($step['state'] === 'missed')
                            <x-app.icon name="phone-off" class="mt-1 size-4 shrink-0 text-zinc-400" />
                        @else
                            <x-app.icon name="phone" class="text-brand mt-1 size-4 shrink-0" />
                        @endif

                        <span>
                            <span class="text-xs font-medium text-zinc-500 block">
                                {{ __('portal.onboarding.done.explainer.counter', ['step' => $index + 1, 'total' => count($steps)]) }}
                            </span>
                            {{ $step['title'] }}
                        </span>
                    </p>

                    <p class="mt-2 text-sm text-zinc-600">{{ $step['text'] }}</p>

                    @if ($step['charge'] !== null)
                        <p @class([
                            'mt-3 inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-sm font-semibold tabular-nums',
                            'bg-emerald-50 text-emerald-700' => $step['state'] === 'answered',
                            'bg-brand-50 text-brand' => $step['state'] !== 'answered',
                        ])>
                            <x-app.icon name="coins" class="size-4 shrink-0" />
                            {{ $step['charge'] }}
                        </p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Die Guthabenzahl. Sie laeuft im zweiten Schritt herunter und steht im
         dritten wieder oben -- der verpasste Lead kostet nichts. Der Server
         gibt den Anfangsstand im Markup mit, damit die Zahl ohne Skript nicht
         leer bleibt. --}}
    <p class="billing-explainer__balance mt-4">
        <span class="text-xs text-zinc-500">{{ __('portal.onboarding.done.explainer.balance') }}</span>
        <b
            class="billing-explainer__amount"
            x-text="amount"
            x-bind:class="{ 'is-counting': step === 1 }"
        >{{ \App\Support\Money::format($animation['from']) }}</b>
    </p>

    {{-- Navigation: Punkte und "Weiter". Beide sind echte Knoepfe und nicht
         nur Flaechen -- mit der Tastatur blaettert sich die Erklaerung genauso
         durch wie mit dem Finger. --}}
    <div class="mt-4 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2" role="group" aria-label="{{ __('portal.onboarding.done.explainer.heading') }}">
            @foreach ($steps as $index => $step)
                <button
                    type="button"
                    @class(['billing-explainer__dot', 'is-active' => $index === 0])
                    aria-current="{{ $index === 0 ? 'true' : 'false' }}"
                    x-on:click="go({{ $index }})"
                    x-bind:aria-current="step === {{ $index }} ? 'true' : 'false'"
                    x-bind:class="{ 'is-active': step === {{ $index }} }"
                    wire:key="explainer-dot-{{ $index }}"
                >
                    <span class="sr-only">
                        {{ __('portal.onboarding.done.explainer.go_to', ['step' => $index + 1]) }}
                    </span>
                </button>
            @endforeach
        </div>

        <button type="button" class="btn-ghost" x-on:click="next()">
            <span x-show="! isLast">{{ __('portal.onboarding.done.explainer.next') }}</span>
            <span x-show="isLast" style="display: none">{{ __('portal.onboarding.done.explainer.restart') }}</span>
        </button>
    </div>
</div>
