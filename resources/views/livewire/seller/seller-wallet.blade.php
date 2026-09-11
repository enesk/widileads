{{-- LP-WALLET-012: Einnahmen und Buchungen des Verkaeufers.

     "In Reservierung" steht bewusst neben und nicht im Guthaben: Es ist eine
     Prognose aus offenen Leadkaeufen, kein Geld. --}}
<div class="flex flex-col gap-6">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('marketplace.wallet.seller.balance.available') }}
            </p>
            <p class="mt-1 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">
                {{ $this->money($availableCents) }}
            </p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                {{ __('marketplace.wallet.seller.balance.available_hint') }}
            </p>
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('marketplace.wallet.seller.balance.pending') }}
            </p>
            <p class="mt-1 flex flex-wrap items-baseline gap-2">
                <span class="text-3xl font-bold tracking-tight text-gray-500 dark:text-gray-400">
                    {{ $this->money($pendingCents) }}
                </span>
                <x-filament::badge color="warning">
                    {{ __('marketplace.wallet.seller.balance.pending_badge') }}
                </x-filament::badge>
            </p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                {{ __('marketplace.wallet.seller.balance.pending_hint') }}
            </p>
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('marketplace.wallet.seller.balance.earnings', ['days' => $earningsDays]) }}
            </p>
            <p class="mt-1 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">
                {{ $this->money($earningsCents) }}
            </p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                {{ __('marketplace.wallet.seller.balance.earnings_hint') }}
            </p>
        </x-filament::section>
    </div>

    <x-filament::section :heading="__('marketplace.wallet.seller.history.heading')">
        @if ($transactions->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('marketplace.wallet.seller.history.empty') }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-4 font-medium">{{ __('marketplace.wallet.seller.history.date') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('marketplace.wallet.seller.history.type') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('marketplace.wallet.seller.history.description') }}</th>
                            <th class="py-2 pr-4 text-right font-medium">{{ __('marketplace.wallet.seller.history.amount') }}</th>
                            <th class="py-2 text-right font-medium">{{ __('marketplace.wallet.seller.history.balance_after') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($transactions as $transaction)
                            <tr>
                                <td class="whitespace-nowrap py-2 pr-4 text-gray-500 dark:text-gray-400">
                                    {{ $transaction->created_at?->translatedFormat('d.m.Y H:i') }}
                                </td>
                                <td class="py-2 pr-4">
                                    <x-filament::badge :color="$transaction->amount_cents < 0 ? 'gray' : 'success'">
                                        {{ __('marketplace.wallet.types.'.$transaction->type->value) }}
                                    </x-filament::badge>
                                </td>
                                <td class="py-2 pr-4 text-gray-950 dark:text-white">
                                    {{ $transaction->description }}
                                </td>
                                <td @class([
                                    'whitespace-nowrap py-2 pr-4 text-right font-medium tabular-nums',
                                    'text-danger-600 dark:text-danger-400' => $transaction->amount_cents < 0,
                                    'text-success-600 dark:text-success-400' => $transaction->amount_cents > 0,
                                ])>
                                    {{ $this->signedMoney($transaction->amount_cents) }}
                                </td>
                                <td class="whitespace-nowrap py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                    {{ $this->money($transaction->balance_after_cents) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($hasMore)
                <div class="mt-4">
                    <x-filament::button wire:click="showMore" color="gray" size="sm" outlined>
                        {{ __('marketplace.wallet.seller.history.show_more') }}
                    </x-filament::button>
                </div>
            @endif
        @endif
    </x-filament::section>
</div>
