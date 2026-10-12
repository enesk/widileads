{{--
    Wizard-Schritt 3: die Zahlungsweise (LP-ONBOARD-010).

    Steht in der Karte der Wizard-Huelle; Ueberschrift, Illustration und der
    Knopf "Weiter zur Zahlung" gehoeren ihr. Hier stehen die Auswahlkarten, die
    Rechnung in Lead-Symbolen, die Hinweiszeile und der Verweis "Spaeter".

    **Warum Alpine und nicht nur wire:model.** Eine Karte muss im selben
    Moment gewaehlt aussehen, in dem sie angeklickt wird -- nicht nach einer
    Serverantwort. Die Wahl liegt deshalb doppelt: in Alpine fuer das Aussehen
    und ueber $wire.$set im Zustand der Komponente, aber ohne eigene Anfrage
    (dritter Parameter false). Mitgeschickt wird sie mit der naechsten Anfrage
    -- und das ist genau der Klick auf "Weiter zur Zahlung", der sie prueft.

    Die Karte ist ein <button aria-pressed> und keine Auswahlschaltflaeche mit
    Beschriftung: Der ganze Kasten ist die Klickflaeche.

    Erwartete Daten:
      $packages      euro, leads, popular
      $paygEnabled   ist Pay as you go produktiv?
      $paygChoice    der Wert der Pay-as-you-go-Karte
      $paygSurcharge Aufschlagsatz als Text, etwa "7,5"
      $maxLeadIcons  wie viele Symbole die Reihe zeigt, bevor "+x" kommt
--}}
<div>
    <div
        x-data="{
            choice: @js($choice),
            select(value) {
                this.choice = value;
                /* Dritter Parameter false: setzen, ohne eine Anfrage
                   auszuloesen. Geprueft wird erst bei 'Weiter zur Zahlung'. */
                $wire.$set('choice', value, false);
            },
            explaining: false,
        }"
    >
        {{-- Auf 360 Pixeln liegen die Karten uebereinander, ab sm nebeneinander
             -- drei Pakete passen dort als Spalten, vier brechen in die zweite
             Reihe um. --}}
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($packages as $package)
                @php($value = (string) $package['euro'])

                {{-- Der Anfangszustand steht im Markup und wird von Alpine nur
                     weitergefuehrt: Haengte er allein an Alpine, saehe die
                     vorausgewaehlte Karte bis zum ersten Skriptdurchlauf
                     ungewaehlt aus. --}}
                <button
                    type="button"
                    x-on:click="select(@js($value))"
                    x-bind:aria-pressed="choice === @js($value)"
                    aria-pressed="{{ $choice === $value ? 'true' : 'false' }}"
                    x-bind:class="{ 'border-brand bg-brand-50': choice === @js($value) }"
                    @class([
                        'card-interactive relative flex min-h-[44px] flex-col items-start gap-1 p-4 text-left',
                        'border-brand bg-brand-50' => $choice === $value,
                    ])
                >
                    @if ($package['popular'])
                        <span class="pill-solid absolute right-3 top-3">{{ __('portal.onboarding.payment.popular') }}</span>
                    @endif

                    <span class="text-2xl font-bold tabular-nums text-zinc-900">
                        {{ __('portal.onboarding.payment.amount', ['amount' => $package['euro']]) }}
                    </span>

                    <span class="text-sm text-zinc-500">
                        {{ trans_choice('portal.onboarding.payment.covers', $package['leads'], ['count' => $package['leads']]) }}
                    </span>

                    {{-- Die Rechnung zum Anschauen: ein Symbol je Lead. Mehr
                         als $maxLeadIcons sagen nichts mehr, der Rest steht als
                         "+x" daneben. Die Reihe bricht um, damit sie auf 360
                         Pixeln nicht aus der Karte laeuft; die Zahl darueber
                         sagt dasselbe, deshalb ist sie fuer die
                         Vorlesesoftware verborgen. --}}
                    <span class="mt-3 flex flex-wrap items-center gap-1" aria-hidden="true">
                        @for ($icon = 0; $icon < min($package['leads'], $maxLeadIcons); $icon++)
                            <x-app.icon name="leads" class="size-4 text-brand" />
                        @endfor

                        @if ($package['leads'] > $maxLeadIcons)
                            <span class="text-xs font-medium text-zinc-500">
                                {{ __('portal.onboarding.payment.more_leads', ['count' => $package['leads'] - $maxLeadIcons]) }}
                            </span>
                        @endif
                    </span>
                </button>
            @endforeach

            {{-- Pay as you go nur, wenn das Verfahren laeuft
                 (config('wallet.postpaid.enabled')). Kein Platzhaltertext:
                 Eine Zahlungsweise anzukuendigen, die es nicht gibt, ist eine
                 Frage, die der Kaeufer nicht beantworten kann. --}}
            @if ($paygEnabled)
                <button
                    type="button"
                    x-on:click="select(@js($paygChoice))"
                    x-bind:aria-pressed="choice === @js($paygChoice)"
                    aria-pressed="{{ $choice === $paygChoice ? 'true' : 'false' }}"
                    x-bind:class="{ 'border-brand bg-brand-50': choice === @js($paygChoice) }"
                    @class([
                        'card-interactive relative flex min-h-[44px] flex-col items-start gap-1 p-4 text-left',
                        'border-brand bg-brand-50' => $choice === $paygChoice,
                    ])
                >
                    <x-app.icon name="card" class="size-6 text-brand" />

                    <span class="mt-1 font-semibold text-zinc-900">
                        {{ __('portal.onboarding.payment.payg.title') }}
                    </span>

                    <span class="text-sm text-zinc-500">
                        {{ __('portal.onboarding.payment.payg.text') }}
                    </span>

                    {{-- Statt der Lead-Reihe: Bei Pay as you go gibt es keinen
                         Betrag, aus dem sich ein Gegenwert rechnen liesse. Hier
                         steht deshalb, was der Kaeufer als Naechstes tut. --}}
                    <span class="mt-3 inline-flex items-center gap-1.5 text-xs font-medium text-zinc-500">
                        <x-app.icon name="bank" class="size-4 shrink-0" />
                        {{ __('portal.onboarding.payment.payg.hint', ['percent' => $paygSurcharge]) }}
                    </span>
                </button>
            @endif
        </div>

        @error('choice')
            <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
        @enderror

        {{-- Die Hinweiszeile: der eine Satz, der die Abrechnung erklaert, und
             der Weg zur langen Fassung. --}}
        <div class="bg-brand-50 mt-5 flex items-start gap-3 rounded-xl p-3">
            <x-ui.illustration name="call" :decorative="true" class="size-10 shrink-0" />
            <p class="text-sm text-zinc-700">
                {{ __('portal.onboarding.payment.billing.note') }}
                <button type="button" class="text-brand underline" x-on:click="explaining = true">
                    {{ __('portal.onboarding.payment.billing.link') }}
                </button>
            </p>
        </div>

        {{-- "Spaeter, erst mal umschauen". Unter dem Primaerbutton gedacht, der
             in der Huelle steht -- auf dem Telefon klebt der naemlich in der
             Aktionsleiste am unteren Rand, und ein Verweis darunter waere nicht
             erreichbar. Hier steht er als letzte Zeile des Schritts. --}}
        <p class="mt-5 text-center md:text-left">
            <button
                type="button"
                class="btn-ghost"
                wire:click="later"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="later">{{ __('portal.onboarding.payment.later') }}</span>
                <span wire:loading wire:target="later">{{ __('portal.onboarding.wizard.working') }}</span>
            </button>
        </p>

        {{-- Die Abrechnungs-Erklaerung als Dialog. Auf dem Telefon von unten,
             ab sm mittig -- dasselbe Blatt wie im Marktplatz. Geschlossen wird
             ueber die Flaeche dahinter, den Knopf oder Escape. Reine
             Browsersache: Es gibt hier nichts zu laden und nichts zu
             speichern. --}}
        <div
            x-show="explaining"
            x-on:keydown.escape.window="explaining = false"
            class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
            style="display: none"
        >
            <div class="absolute inset-0 bg-zinc-900/40" x-on:click="explaining = false" aria-hidden="true"></div>

            <div
                class="relative max-h-[85vh] w-full overflow-y-auto rounded-t-2xl border border-zinc-200 bg-white shadow-lg sm:max-w-md sm:rounded-2xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="billing-explainer-title"
            >
                <div class="space-y-4 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <h2 id="billing-explainer-title" class="text-lg font-semibold text-zinc-900">
                            {{ __('portal.onboarding.payment.billing.heading') }}
                        </h2>
                        <button
                            type="button"
                            class="shrink-0 rounded-lg p-1 text-zinc-400 hover:text-zinc-900"
                            x-on:click="explaining = false"
                            aria-label="{{ __('portal.onboarding.payment.billing.close') }}"
                        >
                            <x-app.icon name="close" class="size-5" />
                        </button>
                    </div>

                    {{-- Der 3-Schritte-Player statt der Textliste
                         (LP-ONBOARD-011). Dieselbe Komponente steht auf dem
                         Abschluss-Screen und auf der Rufnummernseite; ihre
                         Zahlen kommen aus config('lead_calls.*').

                         Ohne automatische Vorfuehrung: Der Dialog steht im
                         geschlossenen Zustand mit im Markup, und ein Player,
                         der dahinter weiterlaeuft, stuende beim Oeffnen schon
                         auf Schritt drei. --}}
                    @livewire('portal.onboarding.billing-explainer', ['autoplay' => false])

                    <button type="button" class="btn-secondary w-full" x-on:click="explaining = false">
                        {{ __('portal.onboarding.payment.billing.understood') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
