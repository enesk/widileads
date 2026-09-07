{{-- FB-060: Kaeufersicht des Operators. --}}
<x-filament-panels::page>
    @php($rows = $this->rows())
    @php($average = $this->averageComplaintRate())

    <div class="space-y-4">
        <p class="text-sm opacity-70">
            {{ __('marketplace.overview.description', [
                'average' => number_format($average * 100, 1, ',', '.'),
                'points' => $this->flagThreshold(),
            ]) }}
        </p>

        @if ($rows === [])
            <p>{{ __('marketplace.overview.empty') }}</p>
        @else
            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="p-3 text-left">{{ __('marketplace.overview.buyer') }}</th>
                            <th class="p-3 text-right">{{ __('marketplace.overview.purchases') }}</th>
                            <th class="p-3 text-right">{{ __('marketplace.overview.revenue') }}</th>
                            <th class="p-3 text-right">{{ __('marketplace.overview.complaints') }}</th>
                            <th class="p-3 text-right">{{ __('marketplace.overview.rate') }}</th>
                            <th class="p-3 text-right">{{ __('marketplace.overview.deviation') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-t {{ $row['flagged'] ? 'bg-warning-50 dark:bg-warning-950' : '' }}">
                                <td class="p-3">
                                    {{ $row['tenant']->name }}
                                    @if ($row['flagged'])
                                        <span class="ml-2 rounded bg-warning-500 px-2 py-0.5 text-xs text-white">
                                            {{ __('marketplace.overview.flagged') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3 text-right">{{ $row['purchases'] }}</td>
                                <td class="p-3 text-right">
                                    {{ number_format($row['revenue_cents'] / 100, 2, ',', '.') }}
                                </td>
                                <td class="p-3 text-right">{{ $row['approved_complaints'] }}</td>
                                <td class="p-3 text-right">
                                    {{ number_format($row['complaint_rate'] * 100, 1, ',', '.') }} %
                                </td>
                                <td class="p-3 text-right">
                                    {{ $row['deviation_points'] > 0 ? '+' : '' }}{{ number_format($row['deviation_points'], 1, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="text-xs opacity-70">{{ __('marketplace.overview.hint') }}</p>
        @endif
    </div>
</x-filament-panels::page>
