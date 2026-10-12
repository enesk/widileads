{{-- LP-ONBOARD-015: Der Onboarding-Trichter als horizontale Balken. --}}
@php($funnel = $this->funnel())
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('reports.onboarding.funnel_heading') }}
        </x-slot>

        <x-slot name="description">
            {{ __('reports.onboarding.funnel_hint') }}
        </x-slot>

        <div class="space-y-6">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium" for="onboarding-days">
                        {{ __('reports.onboarding.period') }}
                    </label>
                    <select id="onboarding-days" wire:model.live="days"
                            class="mt-1 rounded-lg border-gray-300 text-sm dark:bg-gray-900">
                        @foreach ($this->periods() as $period)
                            <option value="{{ $period }}">
                                {{ __('reports.onboarding.last_days', ['days' => $period]) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <x-filament::button wire:click="exportCsv" color="gray">
                    {{ __('reports.onboarding.export') }}
                </x-filament::button>
            </div>

            @if ($funnel['registered'] === 0)
                <p class="text-sm">{{ __('reports.onboarding.empty') }}</p>
            @else
                <div class="space-y-2">
                    @foreach ($funnel['stages'] as $index => $stage)
                        <div class="flex items-center gap-3">
                            <span class="w-44 shrink-0 text-sm">
                                {{ $this->stageLabel($stage['key']) }}
                            </span>

                            <div class="h-7 grow overflow-hidden rounded-md bg-gray-100 dark:bg-gray-800">
                                <div class="flex h-full items-center bg-primary-500/80 px-2 text-xs font-medium text-white"
                                     style="width: {{ max(min($stage['percent_of_total'], 100), 0) }}%">
                                    @if ($stage['percent_of_total'] >= 12)
                                        {{ $stage['count'] }}
                                    @endif
                                </div>
                            </div>

                            <span class="w-14 shrink-0 text-right text-sm tabular-nums">
                                {{ $stage['count'] }}
                            </span>

                            <span class="w-20 shrink-0 text-right text-xs tabular-nums opacity-70"
                                  title="{{ __('reports.onboarding.of_previous') }}">
                                @if ($index === 0)
                                    &mdash;
                                @else
                                    {{ number_format($stage['percent_of_previous'], 1, ',', '.') }} %
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>

                <dl class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border p-4">
                        <dt class="text-sm opacity-70">
                            {{ __('reports.onboarding.activation_rate') }}
                        </dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums">
                            {{ number_format($funnel['activation_rate'], 1, ',', '.') }} %
                        </dd>
                        <dd class="mt-1 text-xs opacity-70">
                            {{ __('reports.onboarding.activation_rate_hint', [
                                'activated' => $funnel['activated_in_window'],
                                'registered' => $funnel['registered'],
                                'days' => $this->activationWindowDays(),
                            ]) }}
                            @if ($funnel['activation_window_open'] > 0)
                                {{ trans_choice('reports.onboarding.activation_window_open', $funnel['activation_window_open'], [
                                    'count' => $funnel['activation_window_open'],
                                ]) }}
                            @endif
                        </dd>
                    </div>

                    <div class="rounded-xl border p-4">
                        <dt class="text-sm opacity-70">
                            {{ __('reports.onboarding.median') }}
                        </dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums">
                            {{ $this->formatHours($funnel['median_hours_to_first_purchase']) }}
                        </dd>
                        <dd class="mt-1 text-xs opacity-70">
                            {{ trans_choice('reports.onboarding.median_hint', $funnel['purchased'], [
                                'count' => $funnel['purchased'],
                            ]) }}
                        </dd>
                    </div>
                </dl>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
