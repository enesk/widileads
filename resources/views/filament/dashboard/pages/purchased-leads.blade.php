<x-filament-panels::page>
    {{-- FB-057: Gekaufte Leads im selben Kartenmuster wie der Marktplatz.

         Kontaktdaten stehen hier im Klartext, weil der Kaufbeleg existiert --
         entschieden wird das im LeadContactResolver, nicht in dieser Ansicht. --}}

    @php
        $purchases = $this->purchases();
    @endphp

    <div class="flex flex-col gap-8">

        {{-- Kopf: worum es geht, und der Weg zum Export. --}}
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="max-w-prose">
                <h1 class="text-3xl font-bold tracking-tight text-gray-950 md:text-4xl dark:text-white">
                    {{ __('marketplace.purchased.heading') }}
                </h1>
                <p class="mt-2 text-gray-500 dark:text-gray-400">
                    {{ __('marketplace.purchased.description') }}
                </p>
            </div>

            <x-filament::button
                wire:click="exportCsv"
                color="gray"
                icon="heroicon-o-arrow-down-tray"
                class="w-full shrink-0 sm:w-auto"
            >
                {{ __('marketplace.purchased.export') }}
            </x-filament::button>
        </div>

        {{-- Werkzeugleiste: Ergebniszahl links, Filter und Sortierung rechts. --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ trans_choice('marketplace.purchased.result_count', count($purchases), ['count' => count($purchases)]) }}
            </p>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input
                        type="checkbox"
                        wire:model.live="onlyWithoutFeedback"
                        class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-600 dark:border-white/20 dark:bg-white/5"
                    >
                    {{ __('marketplace.purchased.only_without_feedback') }}
                </label>

                <div>
                    <label for="purchased-sort" class="sr-only">{{ __('marketplace.listing.sort.label') }}</label>
                    <select
                        id="purchased-sort"
                        wire:model.live="sort"
                        class="w-full rounded-lg border-none bg-white py-2 pe-8 ps-3 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 sm:w-auto dark:bg-gray-900 dark:text-white dark:ring-white/10"
                    >
                        @foreach ($this->sortOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if ($purchases === [])
            <div class="rounded-xl bg-white px-6 py-16 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <x-filament::icon icon="heroicon-o-inbox" class="mx-auto h-10 w-10 text-gray-400" />
                <h2 class="mt-4 text-lg font-semibold text-gray-950 dark:text-white">
                    {{ __('marketplace.purchased.empty') }}
                </h2>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($purchases as $purchase)
                    <div
                        wire:key="purchase-{{ $purchase['id'] }}"
                        x-data="{ expanded: false }"
                        class="flex h-full flex-col gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 transition duration-150 hover:ring-gray-950/20 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-white/20"
                    >
                        {{-- Kopf der Karte --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                                    {{ $purchase['name'] }}
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $purchase['purchased_at'] }}
                                </p>
                                <p class="truncate text-sm text-gray-500 dark:text-gray-400">
                                    {{ $purchase['funnel'] }}
                                </p>
                            </div>

                            <x-filament::badge :color="$purchase['contact_status_color']" class="shrink-0">
                                {{ $purchase['contact_status'] }}
                            </x-filament::badge>
                        </div>

                        {{-- Kontaktdaten: der Grund, warum man diese Seite oeffnet. --}}
                        <div class="flex flex-col gap-1.5 text-sm">
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $purchase['phone']) }}"
                               class="flex items-center gap-2 font-medium text-primary-600 hover:underline dark:text-primary-400">
                                <x-filament::icon icon="heroicon-o-phone" class="h-4 w-4 flex-none" />
                                {{ $purchase['phone'] }}
                            </a>
                            <a href="mailto:{{ $purchase['email'] }}"
                               class="flex items-center gap-2 truncate text-primary-600 hover:underline dark:text-primary-400">
                                <x-filament::icon icon="heroicon-o-envelope" class="h-4 w-4 flex-none" />
                                {{ $purchase['email'] }}
                            </a>
                            <p class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                                <x-filament::icon icon="heroicon-o-map-pin" class="h-4 w-4 flex-none text-gray-400" />
                                {{ $purchase['postal_code'] }}
                            </p>
                        </div>

                        {{-- Angaben aus dem Fragebogen --}}
                        @if ($purchase['attributes'] !== [])
                            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                                @foreach ($purchase['attributes'] as $index => $attribute)
                                    <div class="contents" x-show="expanded || {{ $index }} < 4">
                                        <dt class="text-gray-500 dark:text-gray-400">{{ $attribute['label'] }}</dt>
                                        <dd class="font-medium text-gray-950 dark:text-white">{{ $attribute['value'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>

                            @if (count($purchase['attributes']) > 4)
                                <button
                                    type="button"
                                    x-on:click="expanded = ! expanded"
                                    class="-mt-2 self-start text-sm text-primary-600 hover:underline dark:text-primary-400"
                                    x-text="expanded
                                        ? {{ Js::from(__('marketplace.listing.show_less')) }}
                                        : {{ Js::from(trans_choice('marketplace.listing.show_more', count($purchase['attributes']) - 4, ['count' => count($purchase['attributes']) - 4])) }}"
                                ></button>
                            @endif
                        @endif

                        {{-- Stand: Versuche, Rueckmeldung, Reklamation. --}}
                        <div class="flex flex-wrap items-center gap-2 border-t border-gray-200 pt-3 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <span>{{ $purchase['attempts'] }}</span>

                            @if ($purchase['feedback'] !== null)
                                <x-filament::badge color="gray">{{ $purchase['feedback'] }}</x-filament::badge>
                            @endif

                            @if ($purchase['complaint'] !== null)
                                <x-filament::badge color="warning">{{ $purchase['complaint'] }}</x-filament::badge>
                            @endif
                        </div>

{{-- Fuss: Rueckmeldung und Reklamation stehen auf der Detailseite,
                             die Uebersicht fuehrt nur dorthin. --}}
                        <div class="mt-auto flex flex-col gap-2 pt-1 sm:flex-row sm:items-center">
                            <x-filament::button
                                tag="a"
                                :href="$purchase['url']"
                                icon="heroicon-o-arrow-top-right-on-square"
                                class="w-full sm:w-auto"
                            >
                                {{ __('marketplace.purchased.detail.open') }}
                            </x-filament::button>

                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
