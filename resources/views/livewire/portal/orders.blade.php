{{--
    "Bestellungen" im Portal (Entwurf `bestellungen.html`).

    Vom Telefon aus gedacht: Drei Kennzahlen oben, Reiter in einer scrollbaren
    Zeile, Zeilen nach Monat gruppiert mit Monatssumme im Kopf. Auf schmalen
    Bildschirmen wird jede Zeile zweizeilig -- Betrag rechts gross, Status
    darunter, Zahlungsweg und Knopf in der zweiten Reihe.

    Der Knopf richtet sich nach dem Stand und nicht nach einem Einheitsmuster:
    bezahlt fuehrt zur Rechnung, offen und fehlgeschlagen zur Aufladeseite. Ein
    "Anzeigen" mit Auge gibt es nicht -- es gaebe nichts zu zeigen, was nicht
    schon in der Zeile steht.

    Diese Ansicht entscheidet nichts. Alle Werte liegen fertig vor.

    Erwartete Daten:
      $stats       year, count, pending, hasPending
      $tabs        key, label, count, active
      $years       Jahre mit Bestellungen
      $groups      key, title, sum, rows
      $shownCount, $total, $remaining, $nextBatch
      $topUpUrl    Aufladeseite
      $walletUrl   Guthabenverlauf
--}}
<div>

    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl md:text-4xl font-bold tracking-tight text-zinc-900">
            {{ __('marketplace.orders.heading') }}
        </h1>

        <a href="{{ $topUpUrl }}" class="btn-primary size-11 px-0 sm:size-auto sm:px-5" aria-label="{{ __('marketplace.wallet.top_up.title') }}">
            <x-app.icon name="plus" />
            <span class="hidden sm:inline">{{ __('portal.top_up') }}</span>
        </a>
    </div>

    {{-- Drei Zahlen statt einer Tabelle, die jemand addieren muss. Auf dem
         Telefon tragen sie kuerzere Beschriftungen, damit sie nebeneinander
         passen. --}}
    <div class="mt-4 grid grid-cols-3 gap-2 sm:gap-4">
        <div class="card px-3 py-3 sm:px-5 sm:py-4 min-w-0">
            <p class="text-xs sm:text-sm text-zinc-500 truncate">
                <span class="sm:hidden">{{ now()->year }}</span>
                <span class="hidden sm:inline">{{ __('marketplace.orders.stats.year') }}</span>
            </p>
            <p class="font-semibold text-zinc-900 tabular-nums text-base sm:text-2xl whitespace-nowrap">{{ $stats['year'] }}</p>
        </div>

        <div class="card px-3 py-3 sm:px-5 sm:py-4 min-w-0">
            <p class="text-xs sm:text-sm text-zinc-500 truncate">
                <span class="sm:hidden">{{ __('marketplace.orders.stats.count_short') }}</span>
                <span class="hidden sm:inline">{{ __('marketplace.orders.stats.count') }}</span>
            </p>
            <p class="font-semibold text-zinc-900 tabular-nums text-base sm:text-2xl">{{ $stats['count'] }}</p>
        </div>

        {{-- Amber nur, wenn wirklich etwas offen ist. Ein dauerhaft farbiger
             Kasten sagt nichts mehr. --}}
        <div @class(['card px-3 py-3 sm:px-5 sm:py-4 min-w-0', 'border-amber-200' => $stats['hasPending']])>
            <p class="text-xs sm:text-sm text-zinc-500 truncate">
                <span class="sm:hidden">{{ __('marketplace.orders.stats.pending_short') }}</span>
                <span class="hidden sm:inline">{{ __('marketplace.orders.stats.pending') }}</span>
            </p>
            <p class="font-semibold text-zinc-900 tabular-nums text-base sm:text-2xl whitespace-nowrap">{{ $stats['pending'] }}</p>
        </div>
    </div>

    <div class="mt-4 -mx-4 px-4 flex gap-1 overflow-x-auto no-scrollbar" role="tablist" aria-label="{{ __('marketplace.orders.tabs.label') }}" data-tabstrip>
        @foreach ($tabs as $tab)
            <button
                type="button"
                role="tab"
                wire:key="tab-{{ $tab['key'] }}"
                wire:click="setTab('{{ $tab['key'] }}')"
                aria-selected="{{ $tab['active'] ? 'true' : 'false' }}"
                @class([
                    'tab shrink-0 bg-white',
                    'tab-active' => $tab['active'],
                    'border border-zinc-200' => ! $tab['active'],
                ])
            >
                {{ $tab['label'] }}
                <span class="tab-count">{{ $tab['count'] }}</span>
            </button>
        @endforeach
    </div>

    <div class="mt-3 flex gap-2">
        <div class="relative flex-1 min-w-0">
            <label for="orders-search" class="sr-only">{{ __('marketplace.orders.search') }}</label>
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none">
                <x-app.icon name="search" class="size-5 shrink-0" />
            </span>
            <input
                id="orders-search"
                type="search"
                class="input pl-10"
                placeholder="{{ __('marketplace.orders.search_placeholder') }}"
                wire:model.live.debounce.400ms="search"
            >
        </div>

        @if ($years !== [])
            <div class="relative shrink-0">
                <label for="orders-year" class="sr-only">{{ __('marketplace.orders.year') }}</label>
                <select id="orders-year" wire:model.live="year" class="input appearance-none pr-9 w-auto">
                    <option value="">{{ __('marketplace.orders.all_years') }}</option>
                    @foreach ($years as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                <span class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                    <x-app.icon name="chevron-down" class="size-4 text-zinc-400 shrink-0" />
                </span>
            </div>
        @endif
    </div>

    @if ($groups === [])
        <x-app.empty-state
            class="mt-4"
            icon="package"
            :title="__('marketplace.orders.empty.title')"
            :description="__('marketplace.orders.empty.text')"
        >
            <x-app.button variant="primary" :href="$topUpUrl">{{ __('portal.top_up') }}</x-app.button>
        </x-app.empty-state>
    @else
        <div class="mt-4 space-y-3">
            @foreach ($groups as $group)
                <section wire:key="group-{{ $group['key'] }}" class="card overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-zinc-200 bg-zinc-50/60 flex items-baseline justify-between gap-3">
                        <h2 class="font-semibold text-zinc-900">{{ $group['title'] }}</h2>
                        <span class="text-xs sm:text-sm text-zinc-500 tabular-nums">{{ $group['sum'] }}</span>
                    </div>

                    <ul class="divide-y divide-zinc-200">
                        @foreach ($group['rows'] as $row)
                            {{-- Offene Zahlungen liegen leicht amber hinterlegt:
                                 Sie sind das Einzige in dieser Liste, wo noch
                                 etwas zu tun ist. --}}
                            <li wire:key="order-{{ $row['id'] }}" @class(['bg-amber-50/40' => $row['tab'] === 'pending'])>
                                <div class="px-4 py-3 grid grid-cols-[1fr_auto] sm:grid-cols-[7rem_1fr_9rem_auto] gap-x-3 gap-y-1 items-center">
                                    <p class="text-sm text-zinc-500 sm:order-1 tabular-nums">{{ $row['date'] }}</p>

                                    <p class="font-semibold text-zinc-900 tabular-nums text-right sm:text-left sm:order-3">{{ $row['amount'] }}</p>

                                    <p class="text-sm text-zinc-500 sm:order-2 flex items-center gap-2 min-w-0">
                                        <span class="truncate">{{ $row['number'] }}</span>
                                        <span class="hidden md:inline-flex items-center gap-1 text-zinc-400">
                                            <x-app.icon name="card" class="size-4 text-zinc-400 shrink-0" />
                                            {{ $row['method'] }}
                                        </span>
                                    </p>

                                    <div class="flex items-center justify-end gap-2 sm:order-4">
                                        <span @class([
                                            'text-xs py-0.5 whitespace-nowrap',
                                            'pill bg-emerald-50 text-emerald-700' => $row['status']['tone'] === 'emerald',
                                            'pill bg-amber-50 text-amber-800' => $row['status']['tone'] === 'amber',
                                            'pill bg-red-50 text-red-700' => $row['status']['tone'] === 'red',
                                            'pill' => $row['status']['tone'] === 'neutral',
                                        ])>
                                            <x-app.icon :name="$row['status']['icon']" class="size-3.5 shrink-0" />
                                            {{ $row['status']['label'] }}
                                        </span>

                                        <span class="hidden sm:inline">
                                            <x-app.order-action :row="$row" :top-up-url="$topUpUrl" />
                                        </span>
                                    </div>

                                    {{-- Zweite Reihe, nur auf dem Telefon:
                                         Zahlungsweg links, Handlung rechts. --}}
                                    <div class="col-span-2 sm:hidden flex items-center justify-between gap-2 pt-1">
                                        <span class="text-xs text-zinc-400 flex items-center gap-1">
                                            <x-app.icon name="card" class="size-4 text-zinc-400 shrink-0" />
                                            {{ $row['method'] }}
                                        </span>
                                        <x-app.order-action :row="$row" :top-up-url="$topUpUrl" />
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-zinc-500">
                {{ trans_choice('marketplace.orders.count_line', $total, ['count' => $shownCount, 'total' => $total]) }}
            </p>

            @if ($remaining > 0)
                <button type="button" wire:click="loadMore" class="btn-secondary w-full sm:w-auto">
                    {{ trans_choice('marketplace.orders.load_more', $nextBatch, ['count' => $nextBatch]) }}
                </button>
            @endif
        </div>
    @endif

    {{-- Der Satz, der die haeufigste Verwechslung ausraeumt: Diese Liste ist
         Geld hinein, nicht Geld hinaus. --}}
    <p class="mt-5 text-xs text-zinc-500 flex items-start gap-2">
        <x-app.icon name="info" class="size-4 text-zinc-400 shrink-0" />
        <span>
            {{ __('marketplace.orders.footnote') }}
            <a href="{{ $walletUrl }}" class="text-brand hover:underline">{{ __('marketplace.orders.footnote_link') }}</a>
        </span>
    </p>

</div>
