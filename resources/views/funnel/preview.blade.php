{{-- FB-018: Vorschau eines Funnels ueber einen signierten, befristeten Link.
     Gezeigt wird der Entwurfsstand, damit vor dem ersten Publish geprueft
     werden kann. --}}
<x-layouts.funnel :title="__('preview.title', ['name' => $funnel->name])">
    <div class="flex w-full flex-1 flex-col gap-6">
        <header class="rounded-box bg-base-100 p-6 shadow">
            <p class="text-sm uppercase tracking-wide text-base-content/60">{{ __('preview.badge') }}</p>
            <h1 class="mt-1 text-xl font-semibold">{{ $funnel->name }}</h1>
            <p class="mt-2 text-sm text-base-content/70">
                {{ __('preview.status', ['status' => $funnel->status->label()]) }}
            </p>
            <p class="mt-1 text-sm text-base-content/70">{{ __('preview.hint') }}</p>
        </header>

        <section class="rounded-box p-6 shadow {{ $blockers === [] ? 'bg-success/10' : 'bg-warning/10' }}">
            <h2 class="text-base font-semibold">{{ __('preview.publishable.heading') }}</h2>

            @if ($blockers === [])
                <p class="mt-2 text-sm">{{ __('preview.publishable.ready') }}</p>
            @else
                <p class="mt-2 text-sm">{{ __('preview.publishable.blocked') }}</p>
                <ul class="mt-2 list-disc pl-5 text-sm">
                    @foreach ($blockers as $blocker)
                        <li>{{ $blocker }}</li>
                    @endforeach
                </ul>
            @endif
        </section>

        @foreach ($snapshot->steps as $step)
            <section class="rounded-box bg-base-100 p-6 shadow">
                <h2 class="text-base font-semibold">
                    {{ __('preview.step', ['position' => $step->position]) }} — {{ $step->title }}
                </h2>

                @if ($step->description)
                    <p class="mt-1 text-sm text-base-content/70">{{ $step->description }}</p>
                @endif

                <ol class="mt-4 flex flex-col gap-4">
                    @foreach ($step->questions as $question)
                        <li>
                            <p class="font-medium">
                                {{ $question->label }}
                                @if ($question->required)
                                    <span class="text-error" aria-hidden="true">*</span>
                                @endif
                            </p>
                            <p class="text-xs text-base-content/60">
                                <code>{{ $question->fieldKey }}</code> · {{ $question->type }}
                            </p>

                            @if ($question->options !== [])
                                <ul class="mt-2 flex flex-wrap gap-2 text-sm">
                                    @foreach ($question->options as $option)
                                        <li class="rounded-full bg-base-200 px-3 py-1">
                                            {{ $option->label }}
                                            <span class="text-base-content/60">
                                                ({{ __('preview.points', ['points' => $option->points()]) }})
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach

        @if ($snapshot->results !== [])
            <section class="rounded-box bg-base-100 p-6 shadow">
                <h2 class="text-base font-semibold">{{ __('preview.results') }}</h2>
                <ul class="mt-3 flex flex-col gap-2 text-sm">
                    @foreach ($snapshot->results as $result)
                        <li>
                            <span class="font-medium">{{ $result->minScore }}–{{ $result->maxScore }}</span>
                            — {{ $result->title }}
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.funnel>
