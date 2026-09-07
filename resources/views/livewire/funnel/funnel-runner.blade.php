{{-- FB-020: Oeffentliche Funnel-Strecke. Mobile-first ab 360 px, alle
     Bedienelemente mindestens 44 px hoch. --}}
<div class="flex w-full flex-1 flex-col gap-6">
    @if ($this->isArchived())
        <div class="rounded-box bg-base-100 p-6 text-center shadow">
            <h1 class="text-xl font-semibold">{{ __('runtime.archived_title') }}</h1>
            <p class="mt-2 text-base-content/70">{{ __('runtime.archived_body') }}</p>
        </div>
    @else
        <header class="flex flex-col gap-3">
            <h1 class="text-lg font-semibold sm:text-xl">{{ $snapshot->funnel['name'] ?? '' }}</h1>

            @if ($phase !== 'done')
                <div class="h-2 w-full overflow-hidden rounded-full bg-base-300" role="progressbar"
                     aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"
                     aria-label="{{ __('runtime.progress') }}">
                    <div class="h-full bg-primary transition-all" style="width: {{ $progress }}%"></div>
                </div>
            @endif
        </header>

        @if ($phase === 'result' && $result !== null)
            <section class="rounded-box bg-base-100 p-6 shadow">
                <h2 class="text-xl font-semibold">{{ $result->title }}</h2>

                @if ($result->body)
                    <p class="mt-3 whitespace-pre-line text-base-content/80">{{ $result->body }}</p>
                @endif

                <button type="button" wire:click="continueAfterResult"
                        class="btn btn-primary mt-6 min-h-[44px] w-full">
                    {{ $result->ctaLabel ?: __('runtime.continue') }}
                </button>
            </section>
        @elseif ($phase === 'done')
            <section class="rounded-box bg-base-100 p-6 text-center shadow">
                <h2 class="text-xl font-semibold">{{ __('runtime.thanks_title') }}</h2>
                <p class="mt-3 text-base-content/80">{{ __('runtime.thanks_body') }}</p>
            </section>

            @if ($embedded)
                {{-- Sagt der einbettenden Seite Bescheid, damit sie ihr eigenes
                     Tracking anhaengen oder das Overlay schliessen kann. --}}
                <div x-data x-init="document.dispatchEvent(new CustomEvent('funnel:submitted'))"></div>
            @endif
        @elseif ($step !== null)
            {{-- FB-027: Livewire tauscht den Schritt aus, ohne dass die Seite neu
                 laedt. Ohne diese Ansage merkt ein Screenreader-Nutzer nicht,
                 dass sich der Inhalt geaendert hat. --}}
            <p class="sr-only" role="status" aria-live="polite" wire:key="announce-{{ $step->position }}">
                {{ __('runtime.step_announcement', [
                    'current' => $step->position,
                    'total' => count($snapshot->steps),
                    'title' => $step->title,
                ]) }}
            </p>

            <form wire:submit="{{ $phase === 'contact' ? 'submitContact' : 'submitStep' }}"
                  class="flex flex-1 flex-col gap-6">
                <section class="rounded-box bg-base-100 p-5 shadow sm:p-6">
                    <h2 class="text-lg font-semibold" tabindex="-1" wire:key="heading-{{ $step->position }}">{{ $step->title }}</h2>

                    @if ($step->description)
                        <p class="mt-2 text-sm text-base-content/70">{{ $step->description }}</p>
                    @endif

                    <div class="mt-6 flex flex-col gap-6">
                        @foreach ($step->questions as $question)
                            <x-dynamic-component
                                :component="$questionTypes->for($question->questionType())->render()"
                                :question="$question" />
                        @endforeach
                    </div>
                </section>

                {{-- FB-023: Honigtopf. Fuer Menschen unsichtbar und aus der
                     Tabulatorreihenfolge genommen; Bots fuellen es trotzdem. --}}
                <div class="hidden">
                    {{-- Kein aria-hidden auf dem umschliessenden Element: Es
                         enthaelt ein Eingabefeld, und aria-hidden ueber einem
                         fokussierbaren Element ist ein Verstoss gegen WCAG
                         4.1.2. Versteckt wird es ueber "hidden" und tabindex,
                         der Screenreader liest es dadurch ohnehin nicht. --}}
                    <label for="website">{{ __('runtime.honeypot_label') }}</label>
                    <input type="text" id="website" name="website" tabindex="-1"
                           autocomplete="off" wire:model="website">
                </div>

                @if ($submissionBlockedReason !== null)
                    <p class="rounded-box bg-warning/20 p-4 text-sm" role="alert">{{ $submissionBlockedReason }}</p>
                @endif

                <button type="submit" class="btn btn-primary min-h-[44px] w-full">
                    {{ $phase === 'contact' ? __('runtime.submit') : __('runtime.next') }}
                </button>
            </form>
        @endif
    @endif
</div>
