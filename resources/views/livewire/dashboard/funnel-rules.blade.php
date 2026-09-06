<div class="space-y-6">

    {{-- Verzweigungsregeln --}}
    <section class="rounded-xl border border-gray-200 bg-white p-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">{{ __('builder.rules.conditions') }}</h2>
                <p class="text-xs text-gray-500">{{ __('builder.rules.conditions_hint') }}</p>
            </div>
            <button type="button" class="btn btn-xs btn-primary" wire:click="addCondition">
                {{ __('builder.rules.add_condition') }}
            </button>
        </div>

        <div class="mt-3 space-y-2">
            @forelse ($conditions as $id => $condition)
                <div wire:key="condition-{{ $id }}"
                     class="grid items-end gap-2 rounded-lg border border-gray-200 p-3 md:grid-cols-12">
                    <div class="md:col-span-3">
                        <label class="block text-xs font-medium text-gray-700">{{ __('builder.rules.if_question') }}</label>
                        <select class="select select-bordered select-sm mt-1 w-full"
                                wire:model="conditions.{{ $id }}.source_question_id" wire:change="saveCondition({{ $id }})">
                            @foreach ($questions as $question)
                                <option value="{{ $question->id }}">
                                    {{ $question->step->position }}. {{ $question->label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-700">{{ __('builder.rules.operator') }}</label>
                        <select class="select select-bordered select-sm mt-1 w-full"
                                wire:model="conditions.{{ $id }}.operator" wire:change="saveCondition({{ $id }})">
                            @foreach ($operators as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-700">{{ __('builder.rules.value') }}</label>
                        <input type="text" class="input input-bordered input-sm mt-1 w-full"
                               wire:model="conditions.{{ $id }}.value" wire:blur="saveCondition({{ $id }})"
                               @disabled($condition['operator'] === 'answered')
                               placeholder="{{ $condition['operator'] === 'in' ? __('builder.rules.value_list') : '' }}" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-700">{{ __('builder.rules.jump_to') }}</label>
                        <select class="select select-bordered select-sm mt-1 w-full"
                                wire:model="conditions.{{ $id }}.target_step_id" wire:change="saveCondition({{ $id }})">
                            @foreach ($steps as $step)
                                <option value="{{ $step->id }}">{{ $step->position }}. {{ $step->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-700">{{ __('builder.rules.evaluate_at') }}</label>
                        <select class="select select-bordered select-sm mt-1 w-full"
                                wire:model="conditions.{{ $id }}.evaluate_at_step_position"
                                wire:change="saveCondition({{ $id }})">
                            @foreach ($steps as $step)
                                <option value="{{ $step->position }}">{{ $step->position }}. {{ $step->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-1">
                        <label class="block text-xs font-medium text-gray-700">{{ __('builder.rules.priority') }}</label>
                        <input type="number" min="0" max="1000" class="input input-bordered input-sm mt-1 w-full"
                               wire:model="conditions.{{ $id }}.priority" wire:blur="saveCondition({{ $id }})" />
                    </div>

                    <div class="md:col-span-12 flex items-center justify-between">
                        <p class="text-xs text-gray-500">{{ __('builder.rules.evaluate_at_hint') }}</p>
                        <button type="button" class="btn btn-xs btn-error btn-outline"
                                wire:click="deleteCondition({{ $id }})"
                                wire:confirm="{{ __('builder.rules.confirm_delete_condition') }}">
                            {{ __('builder.rules.delete') }}
                        </button>
                    </div>
                    @error('conditions.'.$id.'.priority')
                        <p class="md:col-span-12 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @empty
                <p class="py-4 text-sm text-gray-500">{{ __('builder.rules.no_conditions') }}</p>
            @endforelse
        </div>
    </section>

    {{-- Ergebnis-Screens --}}
    <section class="rounded-xl border border-gray-200 bg-white p-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">{{ __('builder.rules.results') }}</h2>
                <p class="text-xs text-gray-500">
                    {{ __('builder.rules.reachable', ['max' => $highestReachable]) }}
                </p>
            </div>
            <button type="button" class="btn btn-xs btn-primary" wire:click="addResult">
                {{ __('builder.rules.add_result') }}
            </button>
        </div>

        {{-- Bereichsvorschau: eine Spalte je Punktzahl von 0 bis zum Maximum --}}
        <div class="mt-3">
            <div class="flex gap-px overflow-hidden rounded" role="img"
                 aria-label="{{ __('builder.rules.range_preview') }}">
                @for ($score = 0; $score <= $scale; $score++)
                    @php
                        $covered = collect($results)->first(fn ($r) => $score >= (int) $r['min_score'] && $score <= (int) $r['max_score']);
                        $reachable = $score <= $highestReachable;
                    @endphp
                    <div class="h-7 flex-1 text-center text-[10px] leading-7
                                {{ $covered ? 'bg-primary-500 text-white' : ($reachable ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-400') }}"
                         title="{{ $covered ? $covered['title'] : __('builder.rules.uncovered', ['score' => $score]) }}">
                        {{ $score }}
                    </div>
                @endfor
            </div>
            <p class="mt-1 text-xs text-gray-500">{{ __('builder.rules.range_legend') }}</p>
        </div>

        @if (! $report->isValid())
            <div class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3" data-testid="range-report">
                <p class="text-sm font-medium text-amber-900">{{ __('builder.rules.range_problems') }}</p>
                <ul class="mt-1 list-inside list-disc text-sm text-amber-900">
                    @foreach ($report->messages() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-3 space-y-2">
            @forelse ($results as $id => $result)
                <div wire:key="result-{{ $id }}" class="grid items-end gap-2 rounded-lg border border-gray-200 p-3 md:grid-cols-12">
                    <div class="md:col-span-1">
                        <label class="block text-xs font-medium text-gray-700">{{ __('builder.rules.min_score') }}</label>
                        <input type="number" min="0" class="input input-bordered input-sm mt-1 w-full"
                               wire:model="results.{{ $id }}.min_score" wire:blur="saveResult({{ $id }})" />
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-xs font-medium text-gray-700">{{ __('builder.rules.max_score') }}</label>
                        <input type="number" min="0" class="input input-bordered input-sm mt-1 w-full"
                               wire:model="results.{{ $id }}.max_score" wire:blur="saveResult({{ $id }})" />
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-xs font-medium text-gray-700">{{ __('builder.rules.result_title') }}</label>
                        <input type="text" class="input input-bordered input-sm mt-1 w-full"
                               wire:model="results.{{ $id }}.title" wire:blur="saveResult({{ $id }})" />
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-medium text-gray-700">{{ __('builder.rules.cta_label') }}</label>
                        <input type="text" class="input input-bordered input-sm mt-1 w-full"
                               wire:model="results.{{ $id }}.cta_label" wire:blur="saveResult({{ $id }})" />
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-medium text-gray-700">{{ __('builder.rules.cta_url') }}</label>
                        <input type="text" class="input input-bordered input-sm mt-1 w-full"
                               wire:model="results.{{ $id }}.cta_url" wire:blur="saveResult({{ $id }})" />
                    </div>
                    <div class="md:col-span-12">
                        <label class="block text-xs font-medium text-gray-700">{{ __('builder.rules.body') }}</label>
                        <textarea rows="2" class="textarea textarea-bordered textarea-sm mt-1 w-full"
                                  wire:model="results.{{ $id }}.body" wire:blur="saveResult({{ $id }})"></textarea>
                    </div>
                    <div class="md:col-span-12 flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" class="checkbox checkbox-sm"
                                   wire:model="results.{{ $id }}.show_contact_form" wire:change="saveResult({{ $id }})" />
                            {{ __('builder.rules.show_contact_form') }}
                        </label>
                        <button type="button" class="btn btn-xs btn-error btn-outline"
                                wire:click="deleteResult({{ $id }})"
                                wire:confirm="{{ __('builder.rules.confirm_delete_result') }}">
                            {{ __('builder.rules.delete') }}
                        </button>
                    </div>
                    @error('results.'.$id.'.title')
                        <p class="md:col-span-12 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @empty
                <p class="py-4 text-sm text-gray-500">{{ __('builder.rules.no_results') }}</p>
            @endforelse
        </div>
    </section>
</div>
