<div class="grid gap-4 lg:grid-cols-12" wire:key="builder-{{ $funnel->id }}">

    {{-- Links: Schritte --}}
    <section class="lg:col-span-3 rounded-xl border border-gray-200 bg-white p-4">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('builder.steps') }}</h2>
            <button type="button" class="btn btn-xs btn-primary" wire:click="addStep">
                {{ __('builder.add_step') }}
            </button>
        </div>

        <ul class="mt-3 space-y-1"
            x-data="funnelSortable({ onSort: ids => $wire.reorderSteps(ids) })"
            x-init="init($el)">
            @forelse ($steps as $step)
                <li wire:key="step-{{ $step->id }}" data-id="{{ $step->id }}"
                    class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm
                           {{ $step->id === $selectedStepId ? 'bg-primary-50 text-primary-900' : 'hover:bg-gray-50' }}">
                    <span class="cursor-grab select-none text-gray-400" data-drag-handle title="{{ __('builder.drag') }}">⠿</span>
                    <button type="button" class="flex-1 truncate text-left" wire:click="selectStep({{ $step->id }})">
                        {{ $step->position }}. {{ $step->title }}
                    </button>
                    <button type="button" class="text-xs text-red-600 hover:underline"
                            wire:click="deleteStep({{ $step->id }})"
                            wire:confirm="{{ __('builder.confirm_delete_step') }}">
                        {{ __('builder.delete') }}
                    </button>
                </li>
            @empty
                <li class="px-2 py-4 text-sm text-gray-500">{{ __('builder.no_steps') }}</li>
            @endforelse
        </ul>
    </section>

    {{-- Mitte: Fragen des gewaehlten Schritts --}}
    <section class="lg:col-span-5 rounded-xl border border-gray-200 bg-white p-4">
        @if ($selectedStepId === null)
            <p class="text-sm text-gray-500">{{ __('builder.select_step') }}</p>
        @else
            <div>
                <label for="step-title" class="block text-xs font-medium text-gray-700">
                    {{ __('builder.step_title') }}
                </label>
                <input id="step-title" type="text" class="input input-bordered input-sm mt-1 w-full"
                       wire:model.live.debounce.600ms="stepTitle" wire:blur="saveStep" wire:change="saveStep" />
                @error('stepTitle') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mt-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900">{{ __('builder.questions') }}</h2>
                <div class="flex items-center gap-2">
                    <select class="select select-bordered select-xs" x-ref="newType">
                        @foreach ($questionTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-xs btn-primary"
                            x-on:click="$wire.addQuestion($refs.newType.value)">
                        {{ __('builder.add_question') }}
                    </button>
                </div>
            </div>

            <ul class="mt-3 space-y-1"
                x-data="funnelSortable({ onSort: ids => $wire.reorderQuestions(ids) })"
                x-init="init($el)">
                @forelse ($questions as $item)
                    <li wire:key="question-{{ $item->id }}" data-id="{{ $item->id }}"
                        class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm
                               {{ $item->id === $selectedQuestionId ? 'bg-primary-50 text-primary-900' : 'hover:bg-gray-50' }}">
                        <span class="cursor-grab select-none text-gray-400" data-drag-handle title="{{ __('builder.drag') }}">⠿</span>
                        <button type="button" class="flex-1 truncate text-left" wire:click="selectQuestion({{ $item->id }})">
                            {{ $item->label }}
                            <code class="ml-1 text-xs text-gray-500">{{ $item->field_key }}</code>
                        </button>
                        <span class="badge badge-ghost badge-sm">{{ $item->type->label() }}</span>
                        <button type="button" class="text-xs text-red-600 hover:underline"
                                wire:click="deleteQuestion({{ $item->id }})"
                                wire:confirm="{{ __('builder.confirm_delete_question') }}">
                            {{ __('builder.delete') }}
                        </button>
                    </li>
                @empty
                    <li class="px-2 py-4 text-sm text-gray-500">{{ __('builder.no_questions') }}</li>
                @endforelse
            </ul>
        @endif
    </section>

    {{-- Rechts: Eigenschaften der markierten Frage --}}
    <section class="lg:col-span-4 rounded-xl border border-gray-200 bg-white p-4">
        @if ($question === null)
            <p class="text-sm text-gray-500">{{ __('builder.select_question') }}</p>
        @else
            <h2 class="text-sm font-semibold text-gray-900">{{ __('builder.properties') }}</h2>

            @if ($conflictMessage !== null)
                <div class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3" data-testid="conflict">
                    <p class="text-sm text-amber-900">{{ $conflictMessage }}</p>
                    <button type="button" class="btn btn-xs mt-2" wire:click="reloadQuestion">
                        {{ __('builder.reload') }}
                    </button>
                </div>
            @endif

            <div class="mt-3 space-y-3">
                <div>
                    <label for="q-type" class="block text-xs font-medium text-gray-700">{{ __('builder.type') }}</label>
                    <select id="q-type" class="select select-bordered select-sm mt-1 w-full"
                            wire:model.live="questionForm.type" wire:change="saveQuestion">
                        @foreach ($questionTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="q-label" class="block text-xs font-medium text-gray-700">{{ __('builder.label') }}</label>
                    <input id="q-label" type="text" class="input input-bordered input-sm mt-1 w-full"
                           wire:model.live.debounce.600ms="questionForm.label" wire:blur="saveQuestion" />
                    @error('questionForm.label') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="q-key" class="block text-xs font-medium text-gray-700">{{ __('builder.field_key') }}</label>
                    <input id="q-key" type="text" class="input input-bordered input-sm mt-1 w-full font-mono"
                           wire:model.live.debounce.600ms="questionForm.field_key" wire:blur="saveQuestion" />
                    <p class="mt-1 text-xs text-gray-500">{{ __('builder.field_key_hint') }}</p>
                    @error('questionForm.field_key') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="q-help" class="block text-xs font-medium text-gray-700">{{ __('builder.help_text') }}</label>
                    <textarea id="q-help" rows="2" class="textarea textarea-bordered textarea-sm mt-1 w-full"
                              wire:model.live.debounce.600ms="questionForm.help_text" wire:blur="saveQuestion"></textarea>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" class="checkbox checkbox-sm"
                           wire:model.live="questionForm.required" wire:change="saveQuestion" />
                    {{ __('builder.required') }}
                </label>

                <p class="text-xs text-gray-500">
                    {{ __('builder.rendered_by', ['component' => $previewComponent]) }}
                </p>
            </div>

            <div class="mt-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('builder.options') }}</h3>
                    <button type="button" class="btn btn-xs" wire:click="addOption">
                        {{ __('builder.add_option') }}
                    </button>
                </div>
                <ul class="mt-2 space-y-1">
                    @forelse ($options as $option)
                        <li wire:key="option-{{ $option->id }}" class="flex items-center gap-2 text-sm">
                            <span class="flex-1 truncate">{{ $option->label }}</span>
                            <code class="text-xs text-gray-500">{{ $option->value }}</code>
                            <button type="button" class="text-xs text-red-600 hover:underline"
                                    wire:click="deleteOption({{ $option->id }})"
                                    wire:confirm="{{ __('builder.confirm_delete_option') }}">
                                {{ __('builder.delete') }}
                            </button>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500">{{ __('builder.no_options') }}</li>
                    @endforelse
                </ul>
            </div>
        @endif
    </section>

    @vite('resources/js/funnel-builder.js')
</div>
