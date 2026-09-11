<x-filament-panels::page>
    {{-- FB-053: Der Marktplatz als Schaufenster.

         Kontaktdaten stehen hier ausschliesslich so, wie der LeadPresenter sie
         liefert -- diese Ansicht entscheidet nichts ueber Klartext oder
         Maskierung. --}}

    @php
        $leads = $this->leads();
        $balance = $this->balance();
        $topUpUrl = \App\Filament\Dashboard\Pages\WalletTopUp::getUrl();
    @endphp

    <div class="flex flex-col gap-8">

        {{-- Kopf: worum es geht, und was noch im Guthaben steht. --}}
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="max-w-prose">
                <h1 class="text-3xl font-bold tracking-tight text-gray-950 md:text-4xl dark:text-white">
                    {{ __('marketplace.listing.heading') }}
                </h1>
                <p class="mt-2 text-gray-500 dark:text-gray-400">
                    {{ __('marketplace.listing.description') }}
                </p>
                @unless ($this->hasBuyerProfile())
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('marketplace.listing.no_profile') }}
                    </p>
                @endunless
            </div>

            {{-- Guthabenkopf: dieselbe Komponente wie auf der Guthabenseite,
                 damit Stand und Reservierung an beiden Stellen gleich stehen. --}}
            <div class="shrink-0 rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                @livewire('buyer.wallet-balance', ['compact' => true])
            </div>
        </div>

        {{-- Guthaben leer: Ein Hinweis, kein ausgegrautes Raster. --}}
        @if ($balance <= 0)
            <div class="flex items-center gap-3 rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 flex-none" />
                {{ __('marketplace.listing.no_funds') }}
            </div>
        @endif

        {{-- Werkzeugleiste: Ergebniszahl links, Sortierung rechts. --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ trans_choice('marketplace.listing.result_count', count($leads), ['count' => count($leads)]) }}
            </p>

            <div>
                <label for="marketplace-sort" class="sr-only">{{ __('marketplace.listing.sort.label') }}</label>
                <select
                    id="marketplace-sort"
                    wire:model.live="sort"
                    class="w-auto rounded-lg border-none bg-white py-2 pe-8 ps-3 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-gray-900 dark:text-white dark:ring-white/10"
                >
                    @foreach ($this->sortOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($leads === [])
            <div class="rounded-xl bg-white px-6 py-16 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <x-filament::icon icon="heroicon-o-inbox" class="mx-auto h-10 w-10 text-gray-400" />
                <h2 class="mt-4 text-lg font-semibold text-gray-950 dark:text-white">
                    {{ __('marketplace.listing.empty') }}
                </h2>
                <p class="mx-auto mt-1 max-w-prose text-sm text-gray-500 dark:text-gray-400">
                    {{ __('marketplace.listing.empty_hint') }}
                </p>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($leads as $lead)
                    <div
                        wire:key="lead-{{ $lead['id'] }}"
                        x-data="{ expanded: false, busy: false }"
                        :class="busy && 'opacity-60'"
                        class="flex h-full flex-col gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 transition duration-150 hover:ring-gray-950/20 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-white/20"
                    >
                        {{-- Kopf der Karte --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                                    {{ $lead['name'] }}
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400" title="{{ $lead['created_at_exact'] }}">
                                    {{ $lead['created_at'] }}
                                </p>
                            </div>

                            @if ($lead['is_new'])
                                <x-filament::badge color="primary">{{ __('marketplace.listing.badge_new') }}</x-filament::badge>
                            @endif
                        </div>

                        {{-- Standort: fuer einen Kaeufer das wichtigste Kriterium. --}}
                        <p class="flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                            <x-filament::icon icon="heroicon-o-map-pin" class="h-4 w-4 flex-none text-gray-400" />
                            {{ $lead['postal_code'] }}
                        </p>

                        {{-- Angaben aus dem Fragebogen --}}
                        @if ($lead['attributes'] !== [])
                            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                                @foreach ($lead['attributes'] as $index => $attribute)
                                    <div @class(['contents']) x-show="expanded || {{ $index }} < 4">
                                        <dt class="text-gray-500 dark:text-gray-400">{{ $attribute['label'] }}</dt>
                                        <dd class="font-medium text-gray-950 dark:text-white">{{ $attribute['value'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>

                            @if (count($lead['attributes']) > 4)
                                <button
                                    type="button"
                                    x-on:click="expanded = ! expanded"
                                    class="-mt-2 self-start text-sm text-primary-600 hover:underline dark:text-primary-400"
                                    x-text="expanded
                                        ? {{ Js::from(__('marketplace.listing.show_less')) }}
                                        : {{ Js::from(trans_choice('marketplace.listing.show_more', count($lead['attributes']) - 4, ['count' => count($lead['attributes']) - 4])) }}"
                                ></button>
                            @endif
                        @endif

                        {{-- Was man kauft --}}
                        <p class="flex items-center gap-2 border-t border-gray-200 pt-3 text-sm text-gray-400 dark:border-white/10 dark:text-gray-500">
                            <x-filament::icon icon="heroicon-o-lock-closed" class="h-4 w-4 flex-none" />
                            {{ __('marketplace.listing.locked_contact') }}
                        </p>

                        {{-- Fuss: Preis links, Kauf rechts. --}}
                        <div class="mt-auto flex flex-col gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between">
                            <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $lead['price'] }}</span>

                            <x-filament::button
                                icon="heroicon-o-shopping-cart"
                                class="w-full sm:w-auto"
                                :disabled="! $lead['purchasable'] || ! $lead['affordable']"
                                x-on:click="if (confirm({{ Js::from(__('marketplace.purchase.confirm')) }})) { busy = true; $wire.purchase({{ $lead['id'] }}, {{ $lead['price_cents'] }}) }"
                            >
                                {{ __('marketplace.listing.purchase') }}
                            </x-filament::button>
                        </div>

                        {{-- Sagt, warum der Knopf aus ist, und wo es weitergeht.
                             Ein ausgegrauter Knopf ohne Begruendung ist eine
                             Sackgasse. --}}
                        @if ($lead['purchasable'] && ! $lead['affordable'])
                            <p class="-mt-1 text-xs text-danger-600 dark:text-danger-400">
                                {{ __('marketplace.listing.price_exceeds_balance') }}
                                <a href="{{ $topUpUrl }}" class="underline">
                                    {{ __('marketplace.wallet.top_up.title') }}
                                </a>
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
