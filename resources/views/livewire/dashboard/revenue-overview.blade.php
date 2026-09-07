{{-- FB-072: Umsatzuebersicht. Alle Betraege kommen in Cent aus der Datenbank
     und werden erst hier zur Anzeige formatiert. --}}
@php
    $money = fn (int $cents, string $currency) => number_format($cents / 100, 2, ',', '.').' '.($currency === 'EUR' ? '€' : $currency);
@endphp

<div class="flex flex-col gap-6">
    <section class="rounded-box bg-base-100 p-4 shadow">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('operator_revenue.from') }}</span>
                <input type="date" wire:model.live="from" class="input input-bordered">
            </label>
            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('operator_revenue.until') }}</span>
                <input type="date" wire:model.live="until" class="input input-bordered">
            </label>
        </div>
    </section>

    @if ($report['totals'] === [])
        <div class="rounded-box bg-base-100 p-6 text-center shadow text-base-content/60">
            {{ __('operator_revenue.empty') }}
        </div>
    @else
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($report['totals'] as $total)
                <div class="rounded-box bg-base-100 p-4 shadow">
                    <p class="text-sm text-base-content/60">{{ __('operator_revenue.sold') }}</p>
                    <p class="text-2xl font-semibold">{{ $total['sold'] }}</p>
                </div>
                <div class="rounded-box bg-base-100 p-4 shadow">
                    <p class="text-sm text-base-content/60">{{ __('operator_revenue.revenue') }}</p>
                    <p class="text-2xl font-semibold">{{ $money($total['revenue_cents'], $total['currency']) }}</p>
                </div>
                <div class="rounded-box bg-base-100 p-4 shadow">
                    <p class="text-sm text-base-content/60">{{ __('operator_revenue.refunds') }}</p>
                    <p class="text-2xl font-semibold">
                        {{ $total['refunds'] }} &middot; {{ $money($total['refunded_cents'], $total['currency']) }}
                    </p>
                </div>
                <div class="rounded-box bg-base-100 p-4 shadow">
                    <p class="text-sm text-base-content/60">{{ __('operator_revenue.net') }}</p>
                    <p class="text-2xl font-semibold">{{ $money($total['net_cents'], $total['currency']) }}</p>
                </div>
            @endforeach
        </section>

        @foreach ([['by_funnel', __('operator_revenue.by_funnel')], ['by_buyer', __('operator_revenue.by_buyer')]] as [$key, $title])
            <section class="rounded-box bg-base-100 shadow">
                <div class="overflow-x-auto">
                    <table class="table">
                        <caption class="px-4 py-3 text-left text-base font-semibold">{{ $title }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ $title }}</th>
                                <th scope="col" class="text-right">{{ __('operator_revenue.sold') }}</th>
                                <th scope="col" class="text-right">{{ __('operator_revenue.revenue') }}</th>
                                <th scope="col" class="text-right">{{ __('operator_revenue.refunds') }}</th>
                                <th scope="col" class="text-right">{{ __('operator_revenue.net') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report[$key] as $row)
                                <tr wire:key="{{ $key }}-{{ $loop->index }}">
                                    <th scope="row" class="font-medium">{{ $row['label'] }}</th>
                                    <td class="text-right">{{ $row['sold'] }}</td>
                                    <td class="text-right">{{ $money($row['revenue_cents'], $row['currency']) }}</td>
                                    <td class="text-right">
                                        {{ $row['refunds'] }} &middot; {{ $money($row['refunded_cents'], $row['currency']) }}
                                    </td>
                                    <td class="text-right font-semibold">{{ $money($row['net_cents'], $row['currency']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endif
</div>
