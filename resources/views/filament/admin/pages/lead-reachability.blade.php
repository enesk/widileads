{{-- FB-085: Erreichbarkeitsquote je Kaeufer. Reine Ansicht. --}}
<x-filament-panels::page>
    @php($summary = $this->summary())

    <div class="space-y-6">
        <p class="text-sm opacity-70">
            {{ __('call.reachability.description', [
                'average' => number_format($summary['average_rate'] * 100, 1, ',', '.'),
                'points' => $this->flagThreshold(),
            ]) }}
        </p>

        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium" for="from">
                    {{ __('call.reachability.from') }}
                </label>
                <input id="from" type="date" wire:model.live="from"
                       class="mt-1 rounded-lg border-gray-300 dark:bg-gray-900">
            </div>

            <div>
                <label class="block text-sm font-medium" for="to">
                    {{ __('call.reachability.to') }}
                </label>
                <input id="to" type="date" wire:model.live="to"
                       class="mt-1 rounded-lg border-gray-300 dark:bg-gray-900">
            </div>

            <x-filament::button wire:click="exportCsv" color="gray">
                {{ __('call.reachability.export') }}
            </x-filament::button>
        </div>

        @if ($summary['rows'] === [])
            <p>{{ __('call.reachability.empty') }}</p>
        @else
            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="p-3 text-left">{{ __('call.reachability.buyer') }}</th>
                            <th class="p-3 text-right">{{ __('call.reachability.leads_total') }}</th>
                            <th class="p-3 text-right">{{ __('call.reachability.billable') }}</th>
                            <th class="p-3 text-right">{{ __('call.reachability.unreachable') }}</th>
                            <th class="p-3 text-right">{{ __('call.reachability.open') }}</th>
                            <th class="p-3 text-right">{{ __('call.reachability.rate') }}</th>
                            <th class="p-3 text-right">{{ __('call.reachability.deviation') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['rows'] as $row)
                            <tr class="border-t {{ $row['flagged'] ? 'bg-warning-50 dark:bg-warning-950' : '' }}">
                                <td class="p-3">
                                    {{ $row['tenant']?->name ?? __('call.reachability.unknown_buyer') }}
                                    @if ($row['flagged'])
                                        <span class="ml-2 rounded bg-warning-500 px-2 py-0.5 text-xs text-white">
                                            {{ __('call.reachability.flagged') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3 text-right">{{ $row['leads_total'] }}</td>
                                <td class="p-3 text-right">{{ $row['billable'] }}</td>
                                <td class="p-3 text-right">{{ $row['unreachable'] }}</td>
                                <td class="p-3 text-right">{{ $row['open'] }}</td>
                                <td class="p-3 text-right">
                                    {{ number_format($row['unreachable_rate'] * 100, 1, ',', '.') }} %
                                </td>
                                <td class="p-3 text-right">
                                    {{ $row['deviation_points'] > 0 ? '+' : '' }}{{ number_format($row['deviation_points'], 1, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t bg-gray-50 font-semibold dark:bg-gray-900">
                            <td class="p-3">{{ __('call.reachability.total') }}</td>
                            <td class="p-3 text-right">{{ $summary['leads_total'] }}</td>
                            <td class="p-3 text-right">{{ $summary['billable'] }}</td>
                            <td class="p-3 text-right">{{ $summary['unreachable'] }}</td>
                            <td class="p-3 text-right"></td>
                            <td class="p-3 text-right">
                                {{ number_format($summary['average_rate'] * 100, 1, ',', '.') }} %
                            </td>
                            <td class="p-3 text-right"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="text-xs opacity-70">{{ __('call.reachability.hint') }}</p>
        @endif
    </div>
</x-filament-panels::page>
