{{-- FB-071: Zeitverlauf der Leads. Die Reihe kommt als Aggregat aus der
     Datenbank; leere Zeitraeume erscheinen als Null, nicht als Luecke. --}}
<div class="flex flex-col gap-6">
    <section class="rounded-box bg-base-100 p-4 shadow" aria-label="{{ __('timeline.filters') }}">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('timeline.granularity') }}</span>
                <select wire:model.live="granularity" class="select select-bordered">
                    @foreach ($granularities as $value)
                        <option value="{{ $value }}">{{ __('timeline.granularities.'.$value) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('timeline.breakdown') }}</span>
                <select wire:model.live="breakdown" class="select select-bordered">
                    @foreach ($breakdowns as $value)
                        <option value="{{ $value }}">{{ __('timeline.breakdowns.'.$value) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('timeline.funnel') }}</span>
                <select wire:model.live="funnelId" class="select select-bordered">
                    <option value="">{{ __('timeline.all') }}</option>
                    @foreach ($funnels as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('timeline.state') }}</span>
                <select wire:model.live="leadState" class="select select-bordered">
                    <option value="">{{ __('timeline.all') }}</option>
                    @foreach ($states as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('timeline.from') }}</span>
                <input type="date" wire:model.live="from" class="input input-bordered">
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('timeline.until') }}</span>
                <input type="date" wire:model.live="until" class="input input-bordered">
            </label>
        </div>
    </section>

    @if ($report['total'] === 0)
        <div class="rounded-box bg-base-100 p-6 text-center shadow text-base-content/60">
            {{ __('timeline.empty') }}
        </div>
    @else
        <section class="rounded-box bg-base-100 shadow">
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <caption class="sr-only">{{ __('timeline.heading') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('timeline.series') }}</th>
                            @foreach ($report['buckets'] as $bucket)
                                <th scope="col" class="whitespace-nowrap text-right">{{ $bucket }}</th>
                            @endforeach
                            <th scope="col" class="text-right">{{ __('timeline.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['series'] as $series)
                            <tr wire:key="series-{{ $loop->index }}">
                                <th scope="row" class="font-medium">{{ $series['label'] }}</th>
                                @foreach ($report['buckets'] as $bucket)
                                    <td class="text-right">{{ $series['values'][$bucket] }}</td>
                                @endforeach
                                <td class="text-right font-semibold">{{ $series['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">{{ __('timeline.total') }}</th>
                            @foreach ($report['buckets'] as $bucket)
                                <td class="text-right">
                                    {{ collect($report['series'])->sum(fn ($series) => $series['values'][$bucket]) }}
                                </td>
                            @endforeach
                            <td class="text-right font-semibold">{{ $report['total'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    @endif
</div>
