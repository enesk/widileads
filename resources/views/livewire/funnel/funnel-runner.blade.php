{{-- FB-020: Oeffentliche Funnel-Strecke. Mobile-first ab 360 px, alle
     Bedienelemente mindestens 44 px hoch. --}}
<div class="flex w-full flex-1 flex-col gap-6">
    @if ($this->isArchived())
        <div class="rounded-box bg-base-100 p-6 text-center shadow">
            <h1 class="text-xl font-semibold">{{ __('funnel.runtime.archived_title') }}</h1>
            <p class="mt-2 text-base-content/70">{{ __('funnel.runtime.archived_body') }}</p>
        </div>
    @else
        <header class="flex flex-col gap-3">
            <h1 class="text-lg font-semibold sm:text-xl">{{ $snapshot->funnel['name'] ?? '' }}</h1>

            @if ($phase !== 'done')
                <div class="h-2 w-full overflow-hidden rounded-full bg-base-300" role="progressbar"
                     aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"
                     aria-label="{{ __('funnel.runtime.progress') }}">
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
                    {{ $result->ctaLabel ?: __('funnel.runtime.continue') }}
                </button>
            </section>
        @elseif ($phase === 'done')
            <section class="rounded-box bg-base-100 p-6 text-center shadow">
                <h2 class="text-xl font-semibold">{{ __('funnel.runtime.thanks_title') }}</h2>
                <p class="mt-3 text-base-content/80">{{ __('funnel.runtime.thanks_body') }}</p>
            </section>
        @elseif ($step !== null)
            <form wire:submit="{{ $phase === 'contact' ? 'submitContact' : 'submitStep' }}"
                  class="flex flex-1 flex-col gap-6">
                <section class="rounded-box bg-base-100 p-5 shadow sm:p-6">
                    <h2 class="text-lg font-semibold">{{ $step->title }}</h2>

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

                <button type="submit" class="btn btn-primary min-h-[44px] w-full">
                    {{ $phase === 'contact' ? __('funnel.runtime.submit') : __('funnel.runtime.next') }}
                </button>
            </form>
        @endif
    @endif
</div>
