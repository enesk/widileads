{{-- FB-059: Monatsabrechnung je Kaeufer. --}}
<x-filament-panels::page>
    @php($statement = $this->statement())
    @php($labels = $this->stateLabels())

    <div class="space-y-6">
        <p class="text-sm opacity-70">{{ __('marketplace.billing.description') }}</p>

        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium" for="buyer">
                    {{ __('marketplace.billing.buyer') }}
                </label>
                <select id="buyer" wire:model.live="buyerId"
                        class="mt-1 rounded-lg border-gray-300 dark:bg-gray-900">
                    @foreach ($this->buyers() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium" for="month">
                    {{ __('marketplace.billing.month') }}
                </label>
                <input id="month" type="month" wire:model.live="month"
                       class="mt-1 rounded-lg border-gray-300 dark:bg-gray-900">
            </div>

            @if ($statement !== null)
                <x-filament::button wire:click="exportCsv" color="gray">
                    {{ __('marketplace.billing.export') }}
                </x-filament::button>
            @endif
        </div>

        @if ($statement === null)
            <p>{{ __('marketplace.billing.no_buyer') }}</p>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border p-4">
                    <h3 class="font-semibold">{{ __('marketplace.billing.leads') }}</h3>
                    <table class="mt-2 w-full text-sm">
                        @foreach ($statement['states'] as $state => $count)
                            <tr>
                                <td class="py-1">{{ $labels[$state] ?? $state }}</td>
                                <td class="py-1 text-right font-medium">{{ $count }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t">
                            <td class="py-1 font-semibold">{{ __('marketplace.billing.purchases') }}</td>
                            <td class="py-1 text-right font-semibold">{{ $statement['purchases'] }}</td>
                        </tr>
                    </table>
                </div>

                <div class="rounded-xl border p-4">
                    <h3 class="font-semibold">{{ __('marketplace.billing.money') }}</h3>
                    <table class="mt-2 w-full text-sm">
                        <tr>
                            <td class="py-1">{{ __('marketplace.billing.revenue') }}</td>
                            <td class="py-1 text-right font-medium">
                                {{ number_format($statement['revenue_cents'] / 100, 2, ',', '.') }}
                                {{ $statement['currency'] }}
                            </td>
                        </tr>
                        <tr>
                            <td class="py-1">{{ __('marketplace.billing.topped_up') }}</td>
                            <td class="py-1 text-right font-medium">
                                {{ number_format($statement['topped_up_cents'] / 100, 2, ',', '.') }}
                                {{ $statement['currency'] }}
                            </td>
                        </tr>
                        <tr>
                            <td class="py-1">{{ __('marketplace.billing.captured') }}</td>
                            <td class="py-1 text-right font-medium">
                                {{ number_format($statement['captured_cents'] / 100, 2, ',', '.') }}
                                {{ $statement['currency'] }}
                            </td>
                        </tr>
                        <tr>
                            <td class="py-1">{{ __('marketplace.billing.refunded') }}</td>
                            <td class="py-1 text-right font-medium">
                                {{ number_format($statement['refunded_cents'] / 100, 2, ',', '.') }}
                                {{ $statement['currency'] }}
                            </td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1">{{ __('marketplace.billing.captured_expected') }}</td>
                            <td @class([
                                    'py-1 text-right font-medium',
                                    'text-danger-600' => $statement['captured_expected_cents'] !== $statement['captured_cents'],
                                ])>
                                {{ number_format($statement['captured_expected_cents'] / 100, 2, ',', '.') }}
                                {{ $statement['currency'] }}
                            </td>
                        </tr>
                    </table>

                    <p class="mt-3 text-xs opacity-70">{{ __('marketplace.billing.money_hint') }}</p>
                </div>
            </div>

            <div class="rounded-xl border p-4">
                <h3 class="font-semibold">{{ __('marketplace.billing.invoices') }}</h3>
                <p class="text-xs opacity-70">{{ __('marketplace.billing.invoices_hint') }}</p>

                @if ($statement['invoices'] === [])
                    <p class="mt-2 text-sm">{{ __('marketplace.billing.no_invoices') }}</p>
                @else
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($statement['invoices'] as $invoice)
                            <li>
                                <a class="underline"
                                   href="{{ route('invoice.generate', ['transactionUuid' => $invoice['transaction_uuid']]) }}">
                                    {{ $invoice['created_at'] }} &middot;
                                    {{ number_format($invoice['amount'] / 100, 2, ',', '.') }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
