{{--
    Rufnummer bestaetigen (Portal Phase 1).

    Markup nach dem Entwurf `rufnummer-bestaetigen.html`. Was dort der Schalter
    `data-go` erledigt, macht hier $state: enter, calling, done. Die
    Schrittanzeige zeigt auf schmalen Bildschirmen nur die Kreise, ab sm auch
    die Beschriftungen.

    Ticket #98: Der Schritt "Code eingeben" ist weg. Er stammte aus dem
    SMS-Entwurf, in dem der Code aufs Telefon kam und im Browser eingetippt
    wurde. Gebaut ist der umgekehrte Weg -- der Code steht hier, eingetippt
    wird er am Telefon --, und damit war das Feld ein Umweg ohne Wirkung: Ueber
    die Bestaetigung entscheidet allein Twilios Rueckmeldung.

    Diese Ansicht entscheidet nichts und bestaetigt nichts: Der Stand einer
    Nummer kommt vom CallerIdService, gesetzt allein beim Anfordern und bei der
    Rueckmeldung von Twilio.
--}}
<div>

    {{-- Der Weg zurueck steht immer da: Seit Ticket #49 haelt die fehlende
         Rufnummernanzeige niemanden mehr auf dieser Seite fest, und ein
         Hinweis ohne Ausweg waere eine Falle, die es nicht gibt. --}}
    <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand hover:underline">
        <x-app.icon name="arrow-left" class="size-4 shrink-0" />
        {{ __('call.caller_id.portal.back') }}
    </a>

    @if ($missingNumber)
        <div role="status" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="font-semibold text-amber-900 flex items-center gap-2">
                <x-app.icon name="alert" class="size-4 shrink-0" />
                {{ __('call.caller_id.portal.locked_title') }}
            </p>
            <p class="text-sm text-amber-900 mt-1">{{ __('call.caller_id.portal.locked_text') }}</p>
        </div>
    @endif

    <div class="mt-3">
        <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('call.caller_id.portal.heading') }}</h1>
        <p class="text-zinc-500 mt-1 max-w-prose">{{ __('call.caller_id.portal.description') }}</p>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem] items-start">

    <div class="card p-5 md:p-8 min-w-0">

        @if ($notice !== null)
            <div role="status" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                {{ $notice }}
            </div>
        @endif

        <ol class="flex items-center gap-3 sm:gap-4 pb-6 mb-6 border-b border-zinc-200" aria-label="{{ __('call.caller_id.portal.steps.enter') }}">
            @php
                $steps = [
                    'enter' => __('call.caller_id.portal.steps.enter'),
                    'calling' => __('call.caller_id.portal.steps.calling'),
                ];
                $order = array_keys($steps);
                $current = $state === 'done' ? count($order) : array_search($state, $order, true);
            @endphp

            @foreach ($order as $index => $key)
                @php $reached = $index < $current; @endphp

                @if ($index > 0)
                    <li @class(['flex-1 h-px', 'bg-brand' => $reached || $index <= $current, 'bg-zinc-200' => ! ($reached || $index <= $current)]) aria-hidden="true"></li>
                @endif

                <li @class(['flex items-center gap-2 text-sm font-medium', 'text-zinc-900' => $index <= $current, 'text-zinc-500' => $index > $current])>
                    @if ($reached)
                        <span class="size-8 rounded-full bg-brand text-white flex items-center justify-center">
                            <x-app.icon name="check" class="size-4 shrink-0" />
                        </span>
                    @else
                        <span @class([
                            'size-8 rounded-full text-sm font-semibold flex items-center justify-center',
                            'bg-brand text-white' => $index === $current,
                            'bg-zinc-100 text-zinc-500' => $index !== $current,
                        ])>{{ $index + 1 }}</span>
                    @endif
                    <span class="hidden sm:inline">{{ $steps[$key] }}</span>
                </li>
            @endforeach
        </ol>

        {{-- Schritt 1: Nummer --}}
        <form @class(['space-y-5', 'hidden' => $state !== 'enter']) wire:submit="startCall">
            <div>
                <label for="label" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('call.caller_id.portal.label') }}</label>
                <input id="label" class="input" placeholder="{{ __('call.caller_id.portal.label_placeholder') }}" wire:model="label">
                <p class="text-xs text-zinc-500 mt-1">{{ __('call.caller_id.portal.label_hint') }}</p>
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('call.caller_id.portal.number') }}</label>
                <div class="flex gap-2">
                    <div class="relative w-28 shrink-0">
                        <select class="input appearance-none pr-8" aria-label="{{ __('call.caller_id.portal.country') }}" wire:model="countryCode">
                            <option value="+49">🇩🇪 +49</option>
                            <option value="+43">🇦🇹 +43</option>
                            <option value="+41">🇨🇭 +41</option>
                        </select>
                        <x-app.icon name="chevron-down" class="size-4 absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none" />
                    </div>
                    <input id="phone" type="tel" inputmode="tel" autocomplete="tel-national" class="input tabular-nums" placeholder="{{ __('call.caller_id.portal.number_placeholder') }}" wire:model="phone">
                </div>
                <p class="text-xs text-zinc-500 mt-1">{{ __('call.caller_id.portal.number_hint') }}</p>
            </div>
            <div class="rounded-xl bg-brand-50 p-4 text-sm text-brand-700 flex gap-3">
                <x-app.icon name="info" class="size-5 text-brand shrink-0" />
                <span>{{ __('call.caller_id.portal.explainer') }}</span>
            </div>
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-1">
                <a href="{{ $backUrl }}" class="btn-ghost">{{ __('call.caller_id.portal.cancel') }}</a>
                <button type="submit" class="btn-primary">
                    <x-app.icon name="phone" />
                    {{ __('call.caller_id.portal.start') }}
                </button>
            </div>
        </form>

        {{-- Schritt 2: Anruf laeuft. Gefragt wird alle paar Sekunden nach dem
             Stand, den Twilio an den Rueckruf meldet. --}}
        <div @class(['text-center py-4', 'hidden' => $state !== 'calling']) @if ($state === 'calling') wire:poll.3s="refreshStatus" @endif>
            <span class="mx-auto size-16 rounded-full bg-brand-50 text-brand flex items-center justify-center">
                <x-app.icon name="phone" class="size-7 shrink-0" />
            </span>
            <h2 class="mt-5 text-2xl font-semibold text-zinc-900">{{ __('call.caller_id.portal.calling_heading') }}</h2>
            <p class="mt-2 text-zinc-600">
                {!! __('call.caller_id.portal.calling_text', ['number' => '<span class="font-medium text-zinc-900 tabular-nums">'.e($displayNumber).'</span>']) !!}
            </p>
            <p class="mt-4 inline-flex items-center gap-2 text-sm text-zinc-500">
                <x-app.icon name="spinner" class="size-5 animate-spin shrink-0" />
                {{ __('call.caller_id.portal.calling_spinner') }}
            </p>

            {{-- Der Code ist Anzeige und keine Eingabe: Twilio ruft an und
                 laesst ihn am Telefon eintippen. Er ist damit das Wichtigste
                 auf diesem Bildschirm -- wer ihn mit dem Hoerer am Ohr sucht,
                 hat keine Zeit, eine Zeile Text zu lesen. Eine Ziffer je
                 Kaestchen, so wie man sie eintippt. --}}
            @if ($expectedCode !== null)
                <div class="mt-6">
                    <p class="text-sm font-medium text-zinc-500">{{ __('call.caller_id.portal.calling_code_label') }}</p>

                    {{-- Die Ziffern einzeln sind fuer einen Screenreader
                         "4 8 3 9 2 1" und damit schwer zu merken; er bekommt
                         deshalb den Code als einen Satz. --}}
                    <span class="sr-only">{{ __('call.caller_id.portal.calling_code', ['code' => $expectedCode]) }}</span>

                    <div class="mt-2 flex justify-center gap-2 sm:gap-3">
                        @foreach (str_split((string) $expectedCode) as $digit)
                            <span class="flex size-12 sm:size-14 items-center justify-center rounded-xl border border-brand-100 bg-brand-50 text-2xl sm:text-3xl font-bold tabular-nums text-brand-700" aria-hidden="true">{{ $digit }}</span>
                        @endforeach
                    </div>

                    <p class="mt-3 text-sm text-zinc-600">{{ __('call.caller_id.portal.calling_code_hint') }}</p>

                    <p class="mt-1 text-xs text-zinc-500">
                        {{ __('call.caller_id.portal.code_ttl', ['minutes' => (int) config('funnel.call.caller_id.validation_ttl_minutes')]) }}
                    </p>
                </div>
            @endif

            <div class="mt-8 flex justify-center">
                <button type="button" class="btn-ghost" wire:click="goToEnter">{{ __('call.caller_id.portal.other_number') }}</button>
            </div>
            <p class="mt-4 text-xs text-zinc-500">{{ __('call.caller_id.portal.no_call') }}</p>
        </div>

        {{-- Schritt 3: Fertig --}}
        <div @class(['text-center py-4', 'hidden' => $state !== 'done'])>
            <span class="mx-auto size-16 rounded-full bg-brand-50 text-brand flex items-center justify-center">
                <x-app.icon name="check" class="size-7 shrink-0" />
            </span>
            <h2 class="mt-5 text-2xl font-semibold text-zinc-900">{{ __('call.caller_id.portal.done_heading') }}</h2>
            <p class="mt-2 text-zinc-600">
                {!! __('call.caller_id.portal.done_text', [
                    'number' => '<span class="font-medium text-zinc-900 tabular-nums">'.e($displayNumber).'</span>',
                    'app' => e(config('app.wordmark')),
                ]) !!}
            </p>
            <label class="mt-6 inline-flex items-center gap-2.5 text-sm text-zinc-700 cursor-pointer">
                <input type="checkbox" class="size-5 rounded border-zinc-300 text-brand focus:ring-brand" wire:model="isDefault">
                {{ __('call.caller_id.portal.make_default') }}
            </label>
            <div class="mt-8 flex flex-col sm:flex-row justify-center gap-2">
                <a href="{{ $marketplaceUrl }}" class="btn-primary">{{ __('call.caller_id.portal.to_marketplace') }}</a>
                <a href="{{ $numbersUrl }}" class="btn-secondary">{{ __('call.caller_id.portal.to_numbers') }}</a>
            </div>
        </div>
    </div>

    <aside class="space-y-4">
        <section class="card p-5">
            <h2 class="text-sm font-medium text-zinc-500">{{ __('call.caller_id.portal.why_heading') }}</h2>
            <ul class="mt-3 space-y-2.5 text-sm text-zinc-700">
                @foreach (['why_one', 'why_two', 'why_three'] as $reason)
                    <li class="flex gap-2">
                        <span class="text-brand mt-0.5"><x-app.icon name="check" class="size-4 shrink-0" /></span>
                        {{ __('call.caller_id.portal.'.$reason) }}
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- "So wird abgerechnet" als 3-Schritte-Player (LP-ONBOARD-011).
             Dieselbe Komponente steht auf dem Abschluss-Screen des Onboardings
             und im Dialog von Wizard-Schritt 3. Hier gehoert sie hin, weil die
             Frage "und was kostet mich das?" genau beim Bestaetigen der eigenen
             Nummer aufkommt -- direkt davor erklaert die Liste oben, wozu die
             Nummer gebraucht wird.

             Ohne automatische Vorfuehrung: Diese Seite hat mit dem Anruf ihre
             eigene Bewegung, zwei nebeneinander ziehen den Blick auseinander.
             --}}
        <section class="card p-5">
            @livewire('portal.onboarding.billing-explainer', ['autoplay' => false])
        </section>

        @if ($verified !== [])
            <section class="card p-5">
                <h2 class="text-sm font-medium text-zinc-500">{{ __('call.caller_id.portal.verified_heading') }}</h2>
                <ul class="mt-3 divide-y divide-zinc-200 text-sm">
                    @foreach ($verified as $entry)
                        <li class="py-2.5 flex items-center justify-between gap-3">
                            <span>
                                <span class="block font-medium text-zinc-900">{{ $entry['label'] }}</span>
                                <span class="text-zinc-500 tabular-nums">{{ $entry['number'] }}</span>
                            </span>
                            <span class="pill-brand text-xs">{{ __('call.caller_id.portal.default_label') }}</span>
                        </li>
                    @endforeach
                </ul>

                {{-- Nummer gewechselt? Der Weg dafuer steht hier und nur hier
                     (Ticket #2) -- eine zweite Stelle zum Zuruecknehmen waere
                     eine zweite, die irgendwann anders entscheidet. --}}
                @if ($confirmingRelease)
                    {{-- @container statt sm: Die Karte steht in der schmalen
                         Spalte, waehrend das Fenster breit ist -- eine
                         Bildschirmbreite entscheidet hier das Falsche. --}}
                    <div role="alertdialog" aria-labelledby="release-heading" class="@container mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p id="release-heading" class="font-semibold text-amber-900">{{ __('call.caller_id.portal.release.heading') }}</p>

                        {{-- Drei Folgen, nicht eine Floskel: kein Anruf mehr,
                             nur noch die Bestaetigungsseite, und die Fristen
                             laufen weiter. --}}
                        <ul class="mt-2 space-y-1.5 text-sm text-amber-900">
                            <li class="flex gap-2">
                                <x-app.icon name="phone-off" class="size-4 shrink-0 mt-0.5" />
                                <span>{{ __('call.caller_id.portal.release.no_calls') }}</span>
                            </li>
                            <li class="flex gap-2">
                                <x-app.icon name="lock" class="size-4 shrink-0 mt-0.5" />
                                <span>{{ __('call.caller_id.portal.release.portal_locked') }}</span>
                            </li>
                            @if ($openLeadsAtRisk > 0)
                                <li class="flex gap-2">
                                    <x-app.icon name="clock" class="size-4 shrink-0 mt-0.5" />
                                    {{-- Mit Datum, wo es eins gibt: "zwei Leads
                                         mit laufender Frist" ist eine Zahl, "die
                                         naechste laeuft am 19.10. ab" eine
                                         Entscheidungsgrundlage (Ticket #4). --}}
                                    <span>
                                        @if ($nextDeadlineAtRisk !== null)
                                            {{ trans_choice('call.caller_id.portal.release.open_leads_until', $openLeadsAtRisk, ['count' => $openLeadsAtRisk, 'date' => $nextDeadlineAtRisk]) }}
                                        @else
                                            {{ trans_choice('call.caller_id.portal.release.open_leads', $openLeadsAtRisk, ['count' => $openLeadsAtRisk]) }}
                                        @endif
                                    </span>
                                </li>
                            @endif
                        </ul>

                        <div class="mt-4 flex flex-col-reverse @sm:flex-row @sm:justify-end gap-2">
                            <button type="button" class="btn-ghost" wire:click="cancelRelease">{{ __('call.caller_id.portal.release.cancel') }}</button>
                            <button type="button" class="btn-primary" wire:click="releaseNumber" wire:loading.attr="disabled">
                                {{ __('call.caller_id.portal.release.confirm') }}
                            </button>
                        </div>
                    </div>
                @else
                    <button type="button" class="btn-secondary mt-4 w-full sm:w-auto" wire:click="confirmRelease">
                        {{ __('call.caller_id.portal.release.action') }}
                    </button>
                @endif
            </section>
        @endif

        {{-- Hilfe-Kachel, keine Nummern-Kachel: sie steht immer, auch ohne eigene
             Portalnummer. Der Support-Link darf nie an der Nummer haengen. --}}
        <section class="card p-5">
            <h2 class="text-sm font-medium text-zinc-500">{{ __('call.caller_id.portal.trouble_heading') }}</h2>
            <p class="mt-2 text-sm text-zinc-700">
                @if ($portalNumber !== null)
                    {!! __('call.caller_id.portal.trouble_text', ['number' => '<span class="tabular-nums font-medium text-zinc-900">'.e($portalNumber).'</span>']) !!}
                @else
                    {{ __('call.caller_id.portal.trouble_text_no_number') }}
                @endif
            </p>
            <a href="mailto:{{ config('app.support_email') }}" class="block text-sm text-brand hover:underline mt-3">{{ __('call.caller_id.portal.support') }}</a>
        </section>
    </aside>

    </div>
</div>
