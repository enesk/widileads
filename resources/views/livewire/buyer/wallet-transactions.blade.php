{{-- LP-WALLET-011: Transaktionsverlauf des Kaeufers.

     Mobile first: unter md steht jede Buchung als Block mit beschrifteten
     Werten, ab md als Tabellenzeile. Eine echte Tabelle mit fuenf Spalten ist
     auf 375 px nicht lesbar, ein waagerechter Schieber erst recht nicht. --}}
<div class="flex flex-col gap-4">

    {{-- Filter --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex flex-col gap-3 sm:flex-row">
            <div>
                <label for="wallet-type" class="block text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ __('marketplace.wallet.buyer.history.filter.type') }}
                </label>
                <select
                    id="wallet-type"
                    wire:model.live="type"
                    class="mt-1 w-full rounded-lg border-none bg-white py-2 pe-8 ps-3 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 sm:w-auto dark:bg-gray-900 dark:text-white dark:ring-white/10"
                >
                    @foreach ($this->typeOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="wallet-period" class="block text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ __('marketplace.wallet.buyer.history.filter.period') }}
                </label>
                <select
                    id="wallet-period"
                    wire:model.live="period"
                    class="mt-1 w-full rounded-lg border-none bg-white py-2 pe-8 ps-3 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 sm:w-auto dark:bg-gray-900 dark:text-white dark:ring-white/10"
                >
                    @foreach ($this->periodOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ trans_choice('marketplace.wallet.buyer.history.result_count', $transactions->total(), ['count' => $transactions->total()]) }}
        </p>
    </div>

    @if ($transactions->isEmpty())
        <p class="rounded-xl bg-white px-6 py-10 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
            {{ __('marketplace.wallet.buyer.history.empty') }}
        </p>
    @else
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

            {{-- Spaltenkoepfe erst ab md: darunter traegt jeder Wert seine
                 eigene Beschriftung. --}}
            <div class="hidden border-b border-gray-200 px-4 py-2 text-xs font-medium uppercase tracking-wide text-gray-500 md:grid md:grid-cols-[9rem_1fr_8rem_8rem] md:gap-4 dark:border-white/10 dark:text-gray-400">
                <span>{{ __('marketplace.wallet.buyer.history.date') }}</span>
                <span>{{ __('marketplace.wallet.buyer.history.description') }}</span>
                <span class="text-right">{{ __('marketplace.wallet.buyer.history.amount') }}</span>
                <span class="text-right">{{ __('marketplace.wallet.buyer.history.balance_after') }}</span>
            </div>

            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($transactions as $transaction)
                    @php($link = $this->leadLink($transaction))
                    <div
                        wire:key="wallet-tx-{{ $transaction->getKey() }}"
                        class="grid grid-cols-2 gap-x-4 gap-y-2 px-4 py-3 text-sm md:grid-cols-[9rem_1fr_8rem_8rem] md:items-center"
                    >
                        <div class="col-span-2 flex flex-wrap items-center gap-2 md:col-span-1 md:block">
                            <span class="whitespace-nowrap text-gray-500 dark:text-gray-400">
                                {{ $transaction->created_at?->translatedFormat('d.m.Y H:i') }}
                            </span>
                            <span class="md:hidden">
                                <x-filament::badge :color="$this->badgeColor($transaction->type)">
                                    {{ __('marketplace.wallet.types.'.$transaction->type->value) }}
                                </x-filament::badge>
                            </span>
                        </div>

                        <div class="col-span-2 min-w-0 md:col-span-1">
                            <p class="text-gray-950 dark:text-white">{{ $transaction->description }}</p>

                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <span class="hidden md:inline-flex">
                                    <x-filament::badge :color="$this->badgeColor($transaction->type)">
                                        {{ __('marketplace.wallet.types.'.$transaction->type->value) }}
                                    </x-filament::badge>
                                </span>

                                {{-- Reservierung und ihre Aufloesung zeigen auf
                                     denselben Lead und sind darueber als ein
                                     Vorgang erkennbar. --}}
                                @if ($link !== null)
                                    <a
                                        href="{{ $link['url'] }}"
                                        class="text-xs text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        {{ $link['label'] }}
                                    </a>
                                @endif
                            </div>
                        </div>

                        <div class="md:text-right">
                            <span class="block text-xs text-gray-500 md:hidden dark:text-gray-400">
                                {{ __('marketplace.wallet.buyer.history.amount') }}
                            </span>
                            {{-- Reservierung und Auflösung bleiben neutral: Ihr
                                 Vorzeichen gehört zum reservierten Betrag, nicht
                                 zum Saldo. --}}
                            <span @class([
                                'whitespace-nowrap font-medium tabular-nums',
                                'text-gray-500 dark:text-gray-400' => $this->movesReserved($transaction),
                                'text-success-600 dark:text-success-400' => ! $this->movesReserved($transaction) && $transaction->amount_cents > 0,
                                'text-danger-600 dark:text-danger-400' => ! $this->movesReserved($transaction) && $transaction->amount_cents < 0,
                            ])>
                                {{ $this->signedMoney($transaction->amount_cents) }}
                            </span>
                        </div>

                        <div class="md:text-right">
                            <span class="block text-xs text-gray-500 md:hidden dark:text-gray-400">
                                {{ __('marketplace.wallet.buyer.history.balance_after') }}
                            </span>
                            <span class="whitespace-nowrap tabular-nums text-gray-500 dark:text-gray-400">
                                {{ $this->money($transaction->balance_after_cents) }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <p class="text-xs text-gray-400 dark:text-gray-500">
            {{ __('marketplace.wallet.buyer.history.reserved_note') }}
        </p>

        @if ($transactions->hasPages())
            <div>
                {{ $transactions->links() }}
            </div>
        @endif
    @endif
</div>
