{{--
    "Transaktionen" im Portal -- das Journal des Kaeufer-Wallets.

    Gebaut wie "Bestellungen": Kennzahlen oben, Filter darunter, Zeilen nach
    Monat gruppiert, Nachladen statt Blaettern. Der Unterschied liegt in der
    Zeile -- hier steht jede Bewegung mit Vorzeichen und dem Kontostand danach.

    Reservierungen bleiben farblos und tragen keinen Saldo: Sie bewegen den
    reservierten Teil, nicht den Kontostand. Warum, steht einmal unter der
    Liste statt in jeder Zeile.

    Diese Ansicht entscheidet nichts. Alle Werte liegen fertig vor.

    Erwartete Daten:
      $stats         balance_label, balance, reserved, spent, isPostpaid
      $typeOptions   Wert => Beschriftung
      $periodOptions Wert => Beschriftung
      $groups        key, title, sum, rows
      $shownCount, $total, $remaining, $nextBatch
      $reservedNote  Hinweis unter der Liste
      $surchargeHint Aufschlag bei Pay as you go, sonst null
      $topUpUrl, $ordersUrl
--}}
<div>

    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl md:text-4xl font-bold tracking-tight text-zinc-900">
            {{ __('marketplace.wallet.buyer.history.heading') }}
        </h1>

        <a href="{{ $topUpUrl }}" class="btn-primary size-11 px-0 sm:size-auto sm:px-5" aria-label="{{ __('marketplace.wallet.buyer.top_up_heading') }}">
            <x-app.icon name="plus" />
            <span class="hidden sm:inline">{{ __('portal.top_up') }}</span>
        </a>
    </div>

    {{-- Drei Zahlen: woran man ist, was geblockt ist, was der Zeitraum
         gekostet hat. Bei Pay as you go steht vorne der offene Betrag. --}}
    <div class="mt-4 grid grid-cols-3 gap-2 sm:gap-4">
        <div @class(['card px-3 py-3 sm:px-5 sm:py-4 min-w-0', 'border-amber-200' => $stats['isPostpaid']])>
            <p class="text-xs sm:text-sm text-zinc-500 truncate">{{ $stats['balance_label'] }}</p>
            <p class="font-semibold text-zinc-900 tabular-nums text-base sm:text-2xl whitespace-nowrap">{{ $stats['balance'] }}</p>
        </div>

        <div class="card px-3 py-3 sm:px-5 sm:py-4 min-w-0">
            <p class="text-xs sm:text-sm text-zinc-500 truncate">{{ __('marketplace.wallet.buyer.history.portal.stats.reserved') }}</p>
            <p class="font-semibold text-zinc-900 tabular-nums text-base sm:text-2xl whitespace-nowrap">{{ $stats['reserved'] }}</p>
        </div>

        <div class="card px-3 py-3 sm:px-5 sm:py-4 min-w-0">
            <p class="text-xs sm:text-sm text-zinc-500 truncate">{{ __('marketplace.wallet.buyer.history.portal.stats.spent') }}</p>
            <p class="font-semibold text-zinc-900 tabular-nums text-base sm:text-2xl whitespace-nowrap">{{ $stats['spent'] }}</p>
        </div>
    </div>

    <div class="mt-4 flex gap-2">
        <div class="relative flex-1 min-w-0">
            <label for="tx-type" class="sr-only">{{ __('marketplace.wallet.buyer.history.filter.type') }}</label>
            <select id="tx-type" wire:model.live="type" class="input appearance-none pr-9">
                @foreach ($typeOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <span class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                <x-app.icon name="chevron-down" class="size-4 text-zinc-400 shrink-0" />
            </span>
        </div>

        <div class="relative flex-1 min-w-0">
            <label for="tx-period" class="sr-only">{{ __('marketplace.wallet.buyer.history.filter.period') }}</label>
            <select id="tx-period" wire:model.live="period" class="input appearance-none pr-9">
                @foreach ($periodOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <span class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                <x-app.icon name="chevron-down" class="size-4 text-zinc-400 shrink-0" />
            </span>
        </div>
    </div>

    @if ($groups === [])
        <x-app.empty-state
            class="mt-4"
            icon="wallet"
            :title="__('marketplace.wallet.buyer.history.portal.empty_title')"
            :description="__('marketplace.wallet.buyer.history.empty')"
        >
            <x-app.button variant="primary" :href="$topUpUrl">{{ __('portal.top_up') }}</x-app.button>
        </x-app.empty-state>
    @else
        <div class="mt-4 space-y-3">
            @foreach ($groups as $group)
                <section wire:key="tx-group-{{ $group['key'] }}" class="card overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-zinc-200 bg-zinc-50/60 flex items-baseline justify-between gap-3">
                        <h2 class="font-semibold text-zinc-900">{{ $group['title'] }}</h2>
                        <span class="text-xs sm:text-sm text-zinc-500 tabular-nums">{{ $group['sum'] }}</span>
                    </div>

                    <ul class="divide-y divide-zinc-200">
                        @foreach ($group['rows'] as $row)
                            <li wire:key="tx-{{ $row['id'] }}">
                                <div class="px-4 py-3 grid grid-cols-[1fr_auto] sm:grid-cols-[7rem_1fr_auto] gap-x-3 gap-y-1 items-center">
                                    <p class="text-sm text-zinc-500 sm:order-1 tabular-nums">{{ $row['date'] }}</p>

                                    <p @class([
                                        'font-semibold tabular-nums text-right sm:order-3 whitespace-nowrap',
                                        'text-emerald-700' => $row['tone'] === 'emerald',
                                        'text-zinc-900' => $row['tone'] === 'zinc',
                                        'text-zinc-500' => $row['tone'] === 'neutral',
                                    ])>{{ $row['amount'] }}</p>

                                    <div class="col-span-2 sm:col-span-1 sm:order-2 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span @class([
                                                'pill text-xs py-0.5',
                                                'bg-emerald-50 text-emerald-700' => $row['tone'] === 'emerald',
                                            ])>{{ $row['type'] }}</span>

                                            @if ($row['lead'])
                                                <a href="{{ $row['lead']['url'] }}" class="text-sm text-brand hover:underline">{{ $row['lead']['label'] }}</a>
                                            @endif
                                        </div>

                                        <p class="text-sm text-zinc-500 mt-0.5 break-words">{{ $row['description'] }}</p>
                                    </div>

                                    {{-- Der Kontostand steht rechts unter dem
                                         Betrag; bei einer Reservierung steht
                                         stattdessen, was insgesamt geblockt
                                         ist. --}}
                                    <p class="col-start-2 sm:col-start-auto sm:order-4 text-xs text-zinc-400 text-right tabular-nums whitespace-nowrap sm:w-28">
                                        @if ($row['movesReserved'])
                                            {{ __('marketplace.wallet.buyer.history.portal.reserved_after', ['amount' => $row['reserved']]) }}
                                        @else
                                            {{ __('marketplace.wallet.buyer.history.portal.balance_after', ['amount' => $row['balance']]) }}
                                        @endif
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-zinc-500">
                {{ __('marketplace.wallet.buyer.history.portal.count_line', ['count' => $shownCount, 'total' => $total]) }}
            </p>

            @if ($remaining > 0)
                <button type="button" wire:click="loadMore" class="btn-secondary w-full sm:w-auto">
                    {{ trans_choice('marketplace.wallet.buyer.history.portal.load_more', $nextBatch, ['count' => $nextBatch]) }}
                </button>
            @endif
        </div>
    @endif

    <div class="mt-5 space-y-2">
        <p class="text-xs text-zinc-500 flex items-start gap-2">
            <x-app.icon name="info" class="size-4 text-zinc-400 shrink-0" />
            <span>{{ $reservedNote }}</span>
        </p>

        @if ($surchargeHint)
            <p class="text-xs text-zinc-500 flex items-start gap-2">
                <x-app.icon name="info" class="size-4 text-zinc-400 shrink-0" />
                <span>{{ $surchargeHint }}</span>
            </p>
        @endif

        <p class="text-xs text-zinc-500 flex items-start gap-2">
            <x-app.icon name="package" class="size-4 text-zinc-400 shrink-0" />
            <span>
                {{ __('marketplace.wallet.buyer.history.portal.orders_hint') }}
                <a href="{{ $ordersUrl }}" class="text-brand hover:underline">{{ __('marketplace.orders.heading') }}</a>
            </span>
        </p>
    </div>

</div>
