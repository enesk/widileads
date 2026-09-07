{{-- FB-070: Trichter eines Funnels. Alle Zahlen kommen als Aggregate aus der
     Datenbank, nicht aus einer Schleife ueber Ereignisse. --}}
<div class="flex flex-col gap-6">
    <section class="rounded-box bg-base-100 p-4 shadow">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('reports.funnel.funnel') }}</span>
                <select wire:model.live="funnelId" class="select select-bordered">
                    @foreach ($funnels as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('reports.funnel.from') }}</span>
                <input type="date" wire:model.live="from" class="input input-bordered">
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('reports.funnel.until') }}</span>
                <input type="date" wire:model.live="until" class="input input-bordered">
            </label>
        </div>
    </section>

    @if ($report === null)
        <div class="rounded-box bg-base-100 p-6 text-center shadow text-base-content/60">
            {{ __('reports.funnel.no_funnel') }}
        </div>
    @else
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div class="rounded-box bg-base-100 p-4 shadow">
                <p class="text-sm text-base-content/60">{{ __('reports.funnel.views') }}</p>
                <p class="text-2xl font-semibold">{{ $report['views'] }}</p>
            </div>
            <div class="rounded-box bg-base-100 p-4 shadow">
                <p class="text-sm text-base-content/60">{{ __('reports.funnel.submissions') }}</p>
                <p class="text-2xl font-semibold">{{ $report['submissions'] }}</p>
            </div>
            <div class="rounded-box bg-base-100 p-4 shadow">
                <p class="text-sm text-base-content/60">{{ __('reports.funnel.abandoned') }}</p>
                <p class="text-2xl font-semibold">{{ $report['abandoned'] }}</p>
            </div>
            <div class="rounded-box bg-base-100 p-4 shadow">
                <p class="text-sm text-base-content/60">{{ __('reports.funnel.completion_rate') }}</p>
                <p class="text-2xl font-semibold">{{ number_format($report['completion_rate'], 1, ',', '.') }} %</p>
            </div>
        </section>

        <section class="rounded-box bg-base-100 shadow">
            <div class="overflow-x-auto">
                <table class="table">
                    <caption class="sr-only">{{ __('reports.funnel.steps') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('reports.funnel.step') }}</th>
                            <th scope="col">{{ __('reports.funnel.step_views') }}</th>
                            <th scope="col">{{ __('reports.funnel.step_completions') }}</th>
                            <th scope="col">{{ __('reports.funnel.drop_offs') }}</th>
                            <th scope="col">{{ __('reports.funnel.drop_off_rate') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($report['steps'] as $step)
                            @php($worst = $step['drop_off_rate'] >= 50 && $step['views'] > 0)
                            <tr @class(['bg-warning/10' => $worst])>
                                <td>{{ $step['position'] }}. {{ $step['title'] }}</td>
                                <td>{{ $step['views'] }}</td>
                                <td>{{ $step['completions'] }}</td>
                                <td>{{ $step['drop_offs'] }}</td>
                                <td>{{ number_format($step['drop_off_rate'], 1, ',', '.') }} %</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-base-content/60">
                                    {{ __('reports.funnel.no_steps') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
