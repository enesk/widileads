{{--
    Rufnummernbestaetigung, drei Screens in einer Komponente (LP-ONBOARD-005).

    Aufbau wie im Wizard daneben: ab 768 Pixeln zweispaltig -- links die
    Illustration in einer ruhigen Markenflaeche, rechts die Karte. Auf dem
    Telefon liegt die Illustration flach darueber und schrumpft auf 11rem.

    Die drei Zustaende stehen alle drei im Markup und werden mit `hidden`
    ausgeblendet, nicht mit @if entfernt -- wie in
    livewire/portal/caller-id-verification.blade.php. Der Grund ist der
    Code-Screen: Seine sechs Kaestchen haengen an einem Skript, das sie ueber
    ihre Klasse findet. Wuerde Livewire den Block beim Wechsel neu einsetzen,
    waere der Fokus-Sprung ein Rennen gegen den Morph.

    Ausnahme ist der Erfolgs-Screen: Sein Knopf traegt autofocus, und autofocus
    wirkt nur an einem Element, das neu in das Dokument kommt. Er steht deshalb
    hinter @if.
--}}
@php
    /* Der Zaehler steht schon im ersten Aufbau da -- Alpine schreibt ihn
       danach jede Sekunde neu. Ohne diesen Startwert waere die Zeile leer,
       bis das erste Intervall laeuft, und ganz ohne JavaScript bliebe sie es. */
    $clock = intdiv($resendIn, 60).':'.str_pad((string) ($resendIn % 60), 2, '0', STR_PAD_LEFT);
@endphp

<div>
    <div class="container-portal py-8 md:py-12">
        <div class="grid gap-6 md:grid-cols-[18rem_1fr] md:gap-10 md:items-start">

            {{-- Die Illustration sagt dasselbe wie die Ueberschrift daneben,
                 ist also Dekoration und bleibt vor der Vorlesesoftware
                 verborgen. Welches Motiv, entscheidet der Zustand. --}}
            <div class="bg-brand-50 rounded-2xl p-5 md:p-6 flex items-center justify-center">
                <x-ui.illustration
                    :name="$state === 'number' ? 'phone' : ($state === 'code' ? 'sms' : 'success')"
                    :decorative="true"
                    {{-- max-h-44 sind die 11rem des Entwurfs; w-auto haelt das
                         Seitenverhaeltnis, max-w-full faengt den Fall ab, in dem
                         die so errechnete Breite ueber die Spalte laeuft -- unter
                         html.portal mit 125 Prozent Schriftgroesse ist genau das
                         auf 360 Pixeln der Fall. --}}
                    class="max-h-44 max-w-full md:max-h-none w-auto md:w-full"
                />
            </div>

            <div class="w-full md:max-w-xl">
                <x-app.card class="p-5 md:p-6">

                    {{-- Screen 1: Nummer --}}
                    {{-- Der Fokus springt beim Zustandswechsel mit: zurueck auf
                         diesen Screen heisst zurueck in das Nummernfeld. Den
                         umgekehrten Weg (erstes Code-Kaestchen) nimmt
                         resources/js/modules/code-input.js, den Erfolgs-Knopf
                         sein eigenes x-init. --}}
                    <form
                        @class(['hidden' => $state !== 'number'])
                        wire:submit="send"
                        x-data
                        x-on:number-screen-entered.window="$nextTick(() => $refs.phone?.focus())"
                    >
                        <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">
                            {{ __('portal.onboarding.phone.number.heading') }}
                        </h1>

                        <p class="mt-3 text-zinc-600">
                            {{ __('portal.onboarding.phone.number.text') }}
                        </p>

                        <div class="mt-6">
                            <label for="phone" class="block text-sm font-medium text-zinc-700 mb-1">
                                {{ __('portal.onboarding.phone.number.field') }}
                            </label>

                            <div class="flex gap-2">
                                {{-- Laendercode als Text und nicht als Flagge:
                                     Ein Flaggen-Emoji fehlt auf Windows ganz,
                                     und eine Flagge ist ein Staat, kein
                                     Nummernraum. --}}
                                <div class="relative w-28 shrink-0">
                                    <select
                                        class="input appearance-none pr-8 tabular-nums"
                                        aria-label="{{ __('portal.onboarding.phone.number.country') }}"
                                        wire:model="countryCode"
                                    >
                                        @foreach (['DE' => '+49', 'AT' => '+43', 'CH' => '+41'] as $country => $dial)
                                            <option value="{{ $dial }}">{{ $country }} {{ $dial }}</option>
                                        @endforeach
                                    </select>
                                    <x-app.icon name="chevron-down" class="size-4 absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none" />
                                </div>

                                <input
                                    id="phone"
                                    x-ref="phone"
                                    type="tel"
                                    inputmode="tel"
                                    autocomplete="tel"
                                    class="input tabular-nums"
                                    placeholder="{{ __('portal.onboarding.phone.number.placeholder') }}"
                                    wire:model="phone"
                                >
                            </div>

                            @error('phone')
                                <p class="mt-2 flex items-start gap-1.5 text-sm text-red-600" role="alert">
                                    <x-app.icon name="alert-circle" class="size-4 shrink-0 mt-0.5" />
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="btn-primary mt-6 w-full sm:w-auto"
                            wire:loading.attr="disabled"
                            @disabled($resendIn > 0)
                        >
                            <span wire:loading.remove wire:target="send">{{ __('portal.onboarding.phone.number.submit') }}</span>
                            <span wire:loading wire:target="send">{{ __('portal.onboarding.phone.number.sending') }}</span>
                        </button>

                        {{-- Nach einem 429 steht die Wartezeit unter dem
                             gesperrten Knopf. Der Knopf bleibt gesperrt, bis
                             die Seite neu geladen oder die Nummer geaendert
                             wird: Dass die Zeit um ist, sagt der Dienst und
                             nicht dieser Zaehler. --}}
                        @if ($resendIn > 0)
                            <p
                                class="mt-2 text-sm text-zinc-500"
                                data-template="{{ __('portal.onboarding.phone.code.blocked_in', ['countdown' => '%s']) }}"
                                x-data="countdown({{ $resendIn }})"
                                wire:key="blocked-{{ $resendTick }}-{{ $resendIn }}"
                                x-text="$el.dataset.template.replace('%s', clock)"
                            >{{ __('portal.onboarding.phone.code.blocked_in', ['countdown' => $clock]) }}</p>
                        @endif
                    </form>

                    {{-- Screen 2: Code --}}
                    <form @class(['hidden' => $state !== 'code']) wire:submit="verify">
                        <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">
                            {{ __('portal.onboarding.phone.code.heading') }}
                        </h1>

                        {{-- Die Nummer darf nicht mitten in der Maske umbrechen
                             -- "+49 172 •••" ueber der einen und "••67" ueber der
                             naechsten Zeile liest sich wie zwei Nummern. Der Link
                             steht deshalb als eigener Block darunter. --}}
                        <p class="mt-3 text-zinc-600">
                            {!! __($sentByCall ? 'portal.onboarding.phone.code.sent_call' : 'portal.onboarding.phone.code.sent_sms', [
                                'number' => '<span class="whitespace-nowrap tabular-nums text-zinc-900">'.e($maskedPhone ?? '').'</span>',
                            ]) !!}
                        </p>

                        <button type="button" class="mt-1 text-sm text-brand underline hover:no-underline" wire:click="editNumber">
                            {{ __('portal.onboarding.phone.code.edit') }}
                        </button>

                        <div class="mt-6">
                            {{-- Die sechs Kaestchen sind reine Eingabe. Den
                                 Code setzt das Skript zusammen und schreibt ihn
                                 in das verborgene Feld -- nur dieses haengt am
                                 Zustand der Komponente. data-code-submit sagt
                                 dem Skript, dass es bei der sechsten Ziffer
                                 abschicken darf. --}}
                            <fieldset>
                                <legend class="block text-sm font-medium text-zinc-700 mb-1">
                                    {{ __('portal.onboarding.phone.code.field') }}
                                </legend>

                                <div class="code-input" data-code-boxes data-code-submit>
                                    @for ($digit = 1; $digit <= 6; $digit++)
                                        <input
                                            type="text"
                                            inputmode="numeric"
                                            {{-- Kein maxlength am ERSTEN Kaestchen: Es schuetzt
                                                 nichts -- das Skript schneidet ohnehin -- und
                                                 manche Android-Tastaturen kappen den
                                                 Autofill-Wert vorher auf ein Zeichen, dann sind
                                                 die restlichen fuenf Ziffern verloren. --}}
                                            @if ($digit > 1) maxlength="1" @endif
                                            class="code-box"
                                            {{-- autocomplete am ERSTEN Kaestchen: Das Merkmal
                                                 wertet nur Safari (iOS/macOS) aus -- dort steht der
                                                 Code ueber der Tastatur und fuellt beim Antippen
                                                 alle sechs, weil das Skript den Einfuegevorgang
                                                 verteilt. An allen sechs stuenden sechs Vorschlaege
                                                 uebereinander. Chrome fuer Android kennt das
                                                 Merkmal NICHT als Autofill-Quelle; dort laeuft der
                                                 Weg ueber WebOTP (navigator.credentials.get) in
                                                 resources/js/modules/code-input.js, und die SMS
                                                 braucht dafuer die Schlusszeile "@<host> #<code>"
                                                 aus dem Twilio-Verify-Template. --}}
                                            autocomplete="{{ $digit === 1 ? 'one-time-code' : 'off' }}"
                                            aria-label="{{ __('portal.onboarding.phone.code.digit', ['position' => $digit]) }}"
                                        >
                                    @endfor
                                </div>
                            </fieldset>

                            <input type="hidden" data-code-value wire:model="code">

                            @error('code')
                                <p class="mt-2 flex items-start gap-1.5 text-sm text-red-600" role="alert">
                                    <x-app.icon name="alert-circle" class="size-4 shrink-0 mt-0.5" />
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="btn-primary mt-6 w-full sm:w-auto"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="verify">{{ __('portal.onboarding.phone.code.submit') }}</span>
                            <span wire:loading wire:target="verify">{{ __('portal.onboarding.phone.code.checking') }}</span>
                        </button>

                        {{-- Erneut senden. Bis die Sperrfrist um ist, steht hier
                             der Zaehler; danach der Link. Beide Fassungen
                             stehen im Markup, umgeschaltet wird von Alpine --
                             ein wire:poll waere eine Anfrage je Sekunde, nur um
                             eine Ziffer kleiner zu machen.

                             Welche Fassung zu sehen ist, entscheidet schon der
                             Server ueber style="display:none" -- ohne das
                             blitzen beim Laden beide auf, bis Alpine wach ist.
                             Eine Regel fuer [x-cloak] gibt es im Portal-CSS
                             nicht, und sie fuer diese Stelle einzufuehren hiesse,
                             jede Seite davon abhaengig zu machen.

                             Im wire:key steht $resendTick und nicht nur
                             $resendIn: Nach einem erneuten Versand ist die
                             Sperrfrist wieder dieselbe Zahl, der Schluessel
                             blieb gleich, Livewire morphte den Block statt ihn
                             auszutauschen -- und Alpines `left` blieb auf null
                             stehen, also ohne Zaehler und mit klickbarem Link
                             (#55). --}}
                        <div
                            class="mt-5 pt-4 border-t border-zinc-200 text-sm"
                            x-data="countdown({{ $resendIn }})"
                            wire:key="resend-{{ $resendTick }}-{{ $resendIn }}"
                        >
                            <p
                                class="text-zinc-500"
                                data-template="{{ __('portal.onboarding.phone.code.resend_in', ['countdown' => '%s']) }}"
                                x-show="left > 0"
                                x-text="$el.dataset.template.replace('%s', clock)"
                                @style(['display: none' => $resendIn === 0])
                            >{{ __('portal.onboarding.phone.code.resend_in', ['countdown' => $clock]) }}</p>

                            <div
                                class="flex flex-wrap items-center gap-x-4 gap-y-1"
                                x-show="left === 0"
                                @style(['display: none' => $resendIn > 0])
                            >
                                <button type="button" class="text-brand underline hover:no-underline" wire:click="resend" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="resend">{{ __('portal.onboarding.phone.code.resend') }}</span>
                                    <span wire:loading wire:target="resend">{{ __('portal.onboarding.phone.code.resending') }}</span>
                                </button>

                                {{-- Der Anruf ist der Ausweg, nicht der
                                     bequemere Weg: Er erscheint erst nach dem
                                     ersten erneuten Versand. Dieselbe Regel
                                     setzt der Dienst noch einmal durch. --}}
                                @if ($callOffered)
                                    <button type="button" class="text-zinc-500 underline hover:text-zinc-900" wire:click="sendByCall" wire:loading.attr="disabled">
                                        {{ __('portal.onboarding.phone.code.call_instead') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    </form>

                    {{-- Screen 3: Erfolg --}}
                    @if ($state === 'success')
                        <div>
                            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">
                                {{ __('portal.onboarding.phone.success.heading') }}
                            </h1>

                            <p class="mt-3 text-zinc-600">
                                {{ __('portal.onboarding.phone.success.text') }}
                            </p>

                            {{-- autofocus wirkt nur beim Seitenaufbau, dieser
                                 Knopf kommt aber mit einer Livewire-Antwort ins
                                 Dokument. x-init laeuft genau dann -- das
                                 Attribut bleibt daneben stehen, fuer den Fall
                                 eines echten Aufrufs dieses Zustands. --}}
                            <button
                                type="button"
                                class="btn-primary mt-6 w-full sm:w-auto"
                                wire:click="finish"
                                autofocus
                                x-data
                                x-init="$el.focus()"
                            >
                                {{ __('portal.onboarding.phone.success.submit') }}
                            </button>
                        </div>
                    @endif

                </x-app.card>
            </div>
        </div>
    </div>
</div>
