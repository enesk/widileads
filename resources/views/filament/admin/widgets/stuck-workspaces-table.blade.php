{{-- LP-ONBOARD-015: Workspaces, die seit ueber 48 h auf derselben Stufe stehen. --}}
@php($rows = $this->rows())
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('reports.onboarding.stuck_heading') }}
        </x-slot>

        <x-slot name="description">
            {{ __('reports.onboarding.stuck_hint', ['hours' => $this->thresholdHours()]) }}
        </x-slot>

        @if ($rows === [])
            <p class="text-sm">{{ __('reports.onboarding.stuck_empty') }}</p>
        @else
            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="p-3 text-left">{{ __('reports.onboarding.workspace') }}</th>
                            <th class="p-3 text-left">{{ __('reports.onboarding.stage') }}</th>
                            <th class="p-3 text-left">{{ __('reports.onboarding.next_stage') }}</th>
                            <th class="p-3 text-right">{{ __('reports.onboarding.waiting') }}</th>
                            <th class="p-3 text-left">{{ __('reports.onboarding.contact') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-t">
                                <td class="p-3">
                                    @if ($this->userUrl($row['user_id']))
                                        <a href="{{ $this->userUrl($row['user_id']) }}"
                                           class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                            {{ $row['tenant_name'] }}
                                        </a>
                                    @else
                                        {{ $row['tenant_name'] }}
                                    @endif
                                </td>
                                <td class="p-3">{{ $this->stageLabel($row['stage']) }}</td>
                                <td class="p-3 opacity-70">{{ $this->stageLabel($row['next_stage']) }}</td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ $this->formatHours($row['hours']) }}
                                    <span class="block text-xs opacity-60">
                                        {{ $row['since']->translatedFormat('d.m.Y H:i') }}
                                    </span>
                                </td>
                                <td class="p-3">
                                    @if ($row['contact_email'])
                                        <a href="mailto:{{ $row['contact_email'] }}" class="hover:underline">
                                            {{ $row['contact_email'] }}
                                        </a>
                                    @else
                                        <span class="opacity-60">{{ __('reports.onboarding.no_contact') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
