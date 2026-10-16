{{--
    Rufnummernbestaetigung, drei Screens in einer Komponente (LP-ONBOARD-005).

    Aufbau wie im Wizard daneben: ab 768 Pixeln zweispaltig -- links die
    Illustration in einer ruhigen Markenflaeche, rechts die Karte. Auf dem
    Telefon liegt die Illustration flach darueber und schrumpft auf 11rem.

    Ticket #97/#98: Bestaetigt wird per Anruf. Der Code steht auf dieser Seite
    und wird am Telefon eingetippt -- es gibt hier also kein Code-Feld mehr,
    und damit auch kein Skript, das sechs Kaestchen zusammenhaelt
    (resources/js/modules/code-input.js ist mit #98 geloescht, samt WebOTP und
    `one-time-code`: Beides suchte einen Code in einer SMS, die niemand mehr
    verschickt). Der Anruf-Screen steht hinter @if und nicht hinter `hidden`:
    Sein wire:poll soll nicht weiterlaufen, wenn der Nutzer wieder bei der
    Nummer steht.

    Zwei Screens, nicht drei: Nummer eingeben und auf den Anruf warten. Der
    Erfolg ist der dritte nur der Form nach -- er sagt nichts mehr ueber die
    Nummer, sondern fuehrt weiter.

    Der Nummern-Screen bleibt im Markup stehen und wird nur ausgeblendet --
    sein Fokus-Sprung ist sonst ein Rennen gegen den Morph. Der Erfolgs-Screen
    steht hinter @if, weil autofocus nur an einem Element wirkt, das neu in das
    Dokument kommt.
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
                    :name="$state === 'number' ? 'phone' : ($state === 'calling' ? 'phone' : 'success')"
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
                         diesen Screen heisst zurueck in das Nummernfeld. Auf
                         dem Anruf-Screen gibt es nichts zu tippen, der
                         Erfolgs-Knopf holt sich den Fokus ueber sein eigenes
                         x-init. --}}
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
                                data-template="{{ __('portal.onboarding.phone.call.blocked_in', ['countdown' => '%s']) }}"
                                x-data="countdown({{ $resendIn }})"
                                wire:key="blocked-{{ $resendTick }}-{{ $resendIn }}"
                                x-text="$el.dataset.template.replace('%s', clock)"
                            >{{ __('portal.onboarding.phone.call.blocked_in', ['countdown' => $clock]) }}</p>
                        @endif
                    </form>

                    {{-- Screen 2: Der Anruf laeuft --}}
                    @if ($state === 'calling')
                        <div wire:poll.3s="refreshStatus">
                            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">
                                {{ __('portal.onboarding.phone.call.heading') }}
                            </h1>

                            {{-- Die Nummer darf nicht mitten in der Maske umbrechen
                                 -- "+49 172 •••" ueber der einen und "••67" ueber der
                                 naechsten Zeile liest sich wie zwei Nummern. --}}
                            <p class="mt-3 text-zinc-600">
                                {!! __('portal.onboarding.phone.call.text', [
                                    'number' => '<span class="whitespace-nowrap tabular-nums text-zinc-900">'.e($maskedPhone ?? '').'</span>',
                                ]) !!}
                            </p>

                            {{-- Der Code ist Anzeige und keine Eingabe: Eingetippt
                                 wird er am Telefon, sobald die Ansage danach fragt.
                                 Er ist das Wichtigste auf diesem Bildschirm -- wer
                                 ihn mit dem Hoerer am Ohr sucht, hat keine Zeit, eine
                                 Zeile Text zu lesen.

                                 role="status" statt eines Fokus-Sprungs: Der Block
                                 kommt mit einer Livewire-Antwort ins Dokument, und
                                 eine Vorlesesoftware soll den Code hoeren, ohne dass
                                 der Fokus aus dem Ablauf springt. --}}
                            @if ($expectedCode !== null)
                                <div class="mt-6 rounded-2xl bg-brand-50 p-5 text-center" role="status">
                                    <p class="text-sm font-medium text-zinc-700">
                                        {{ __('portal.onboarding.phone.call.code_label') }}
                                    </p>

                                    <p class="mt-1 text-3xl font-bold tabular-nums tracking-[0.3em] text-zinc-900">
                                        {{ $expectedCode }}
                                    </p>

                                    <p class="mt-2 text-sm text-zinc-600">
                                        {{ __('portal.onboarding.phone.call.code_hint') }}
                                    </p>

                                    {{-- Die Frist gehoert an den Code: Nach ihr gilt
                                         er nicht mehr, und das sagt der Dienst
                                         (funnel.call.caller_id.validation_ttl_minutes)
                                         und nicht dieser Text. --}}
                                    <p class="mt-1 text-xs text-zinc-500">
                                        {{ __('portal.onboarding.phone.call.validity', [
                                            'minutes' => (int) config('funnel.call.caller_id.validation_ttl_minutes'),
                                        ]) }}
                                    </p>
                                </div>
                            @endif

                            {{-- Der Fortschritt ist unbestimmt und sieht auch so aus:
                                 Wann Twilio meldet, weiss niemand auf die Sekunde.
                                 Ein Balken, der sich fuellt, waere ein Versprechen,
                                 das wir nicht halten koennen. --}}
                            <p class="mt-5 flex items-center gap-2 text-sm text-zinc-500" role="status">
                                <x-app.icon name="spinner" class="size-4 shrink-0 animate-spin" />
                                {{ __('portal.onboarding.phone.call.waiting') }}
                            </p>

                            @error('code')
                                <p class="mt-2 flex items-start gap-1.5 text-sm text-red-600" role="alert">
                                    <x-app.icon name="alert-circle" class="size-4 shrink-0 mt-0.5" />
                                    {{ $message }}
                                </p>
                            @enderror

                            {{-- Die Frist und danach der Ausweg (Ticket #98).
                                 Solange sie laeuft, steht hier der Zaehler; ist sie
                                 um, stehen die beiden Wege zurueck -- Nummer
                                 korrigieren oder erneut anrufen lassen. Vorher waeren
                                 sie eine Einladung in die Sperre: Der Dienst laesst
                                 den naechsten Anruf erst nach derselben Frist zu
                                 (funnel.call.caller_id.limits.resend_after_seconds).

                                 Beide Fassungen stehen im Markup, umgeschaltet wird
                                 von Alpine -- ein wire:poll je Sekunde nur fuer eine
                                 kleiner werdende Ziffer waere Verschwendung.

                                 Welche Fassung zu sehen ist, entscheidet schon der
                                 Server ueber style="display:none" -- ohne das
                                 blitzen beim Laden beide auf, bis Alpine wach ist.

                                 Im wire:key steht $resendTick und nicht nur
                                 $resendIn: Nach einer erneuten Anforderung ist die
                                 Sperrfrist wieder dieselbe Zahl, der Schluessel
                                 blieb gleich, Livewire morphte den Block statt ihn
                                 auszutauschen -- und Alpines `left` blieb auf null
                                 stehen, also ohne Zaehler und mit klickbarem Link
                                 (#55). --}}
                            <div
                                class="mt-5 pt-4 border-t border-zinc-200 text-sm"
                                x-data="countdown({{ $resendIn }})"
                                wire:key="retry-{{ $resendTick }}-{{ $resendIn }}"
                            >
                                <p
                                    class="text-zinc-500"
                                    data-template="{{ __('portal.onboarding.phone.call.retry_in', ['countdown' => '%s']) }}"
                                    x-show="left > 0"
                                    x-text="$el.dataset.template.replace('%s', clock)"
                                    @style(['display: none' => $resendIn === 0])
                                >{{ __('portal.onboarding.phone.call.retry_in', ['countdown' => $clock]) }}</p>

                                <div
                                    x-show="left === 0"
                                    @style(['display: none' => $resendIn > 0])
                                >
                                    <p class="font-medium text-zinc-900">
                                        {{ __('portal.onboarding.phone.call.no_call_heading') }}
                                    </p>

                                    <p class="mt-1 text-zinc-600">
                                        {{ __('portal.onboarding.phone.call.no_call_text') }}
                                    </p>

                                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1">
                                        <button type="button" class="text-brand underline hover:no-underline" wire:click="resend" wire:loading.attr="disabled">
                                            <span wire:loading.remove wire:target="resend">{{ __('portal.onboarding.phone.call.retry') }}</span>
                                            <span wire:loading wire:target="resend">{{ __('portal.onboarding.phone.call.retrying') }}</span>
                                        </button>

                                        <button type="button" class="text-zinc-500 underline hover:text-zinc-900" wire:click="editNumber">
                                            {{ __('portal.onboarding.phone.call.edit') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

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
