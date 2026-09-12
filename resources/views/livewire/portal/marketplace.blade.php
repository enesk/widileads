{{--
    Der Marktplatz im Portal, Listenfassung (Entwurf `marktplatz-v2.html`).

    Vom Telefon aus gedacht und nach oben erweitert, nicht umgekehrt: Sieben
    Zeilen passen auf einen Bildschirm, wo vorher eine Karte stand. Kein
    Filterpanel im Sichtbereich, sondern eine scrollbare Chipzeile. Kein
    Blaettern, sondern Nachladen.

    Ein Antippen der Zeile oeffnet das Blatt -- auf dem Telefon von unten, ab sm
    als zentrierter Dialog. Es ersetzt Detailseite und Kaufdialog in einem.
    Seine Daten kommen vom Server (`$sheet`) und nicht aus data-Attributen: Der
    Freitext und die vollstaendigen Merkmale haben im Markup jeder Zeile nichts
    zu suchen.

    Diese Ansicht entscheidet nichts. Sie ruft keinen Dienst auf und rechnet
    nicht -- alle Werte liegen fertig vor, Kontaktdaten so, wie der
    LeadPresenter sie geliefert hat.

    Erwartete Daten:
      $leads             Zeilen der Liste
      $resultCount       Treffer insgesamt
      $balance, $hasFunds, $topUpUrl, $surchargeHint, $postpaid, $blocked
      $hasBuyerProfile   sind Kaufkriterien hinterlegt?
      $activeFilters     key, label -- je gesetzter Filter ein Chip
      $activeFilterCount Zahl im Abzeichen am Filterknopf
      $sortOptions, $sortLabel
      $industryOptions, $regionOptions, $priceOptions
      $remaining, $nextBatch   wie viele fehlen, wie viele der Knopf nachlaedt
      $sheet             das offene Blatt oder null
--}}
<div>

    {{-- Kopf: Titel und eine Guthabenkachel mit Plus. Kein Satz, keine grosse
         Karte -- das Guthaben ist eine Zahl, kein Absatz. --}}
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl md:text-4xl font-bold tracking-tight text-zinc-900">
            {{ __('marketplace.listing.heading') }}
        </h1>

        <a href="{{ $topUpUrl }}" class="inline-flex items-center gap-2 min-h-11 pl-3 pr-2 rounded-xl bg-white border border-zinc-200 hover:border-zinc-300">
            <span class="text-sm text-zinc-500 hidden sm:inline">{{ __('portal.balance') }}</span>
            <span class="font-semibold text-zinc-900 tabular-nums">{{ $balance }}</span>
            <span class="size-7 rounded-lg bg-brand-50 text-brand flex items-center justify-center">
                <x-app.icon name="plus" class="size-4 shrink-0" />
            </span>
        </a>
    </div>

    {{-- Rueckmeldung des letzten Kaufversuchs. Bleibt ein Band und wird kein
         Toast: Sie traegt den Weg zur Aufladung, und der waere nach fuenf
         Sekunden weg. --}}
    @if ($message !== null)
        <div
            role="status"
            @class([
                'mt-4 flex flex-wrap items-center gap-3 rounded-xl border p-4 text-sm',
                'border-emerald-200 bg-emerald-50 text-emerald-800' => $messageLevel === 'success',
                'border-amber-200 bg-amber-50 text-amber-900' => $messageLevel !== 'success',
            ])
        >
            <span class="flex-1 min-w-0">{{ $message }}</span>
            @if ($messageActionUrl !== null)
                <a href="{{ $messageActionUrl }}" class="btn-secondary shrink-0">{{ $messageActionLabel }}</a>
            @endif
        </div>
    @endif

    {{-- Ohne Geld geht nichts -- bei Pay as you go heisst das: der
         Kreditrahmen ist erschoepft, und aufladen hilft genauso. --}}
    @unless ($hasFunds)
        <div class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <span class="flex-1 min-w-0">
                {{ $postpaid ? __('portal.postpaid.balance.exhausted') : __('marketplace.listing.no_funds') }}
            </span>
            <a href="{{ $topUpUrl }}" class="btn-secondary shrink-0">{{ __('portal.top_up') }}</a>
        </div>
    @endunless

    {{-- Filterleiste: waagerecht scrollbar, damit sie auf dem Telefon nicht
         umbricht. Der Filterknopf oeffnet das Blatt, die gesetzten Filter
         stehen als Chips daneben und lassen sich einzeln wegwerfen. --}}
    <div class="mt-4 -mx-4 px-4 flex gap-2 overflow-x-auto no-scrollbar">
        <button type="button" class="btn-secondary min-h-10 px-3.5 shrink-0" x-data x-on:click="$refs.filters.hidden = ! $refs.filters.hidden">
            <x-app.icon name="sliders" />
            {{ __('portal.filters.label') }}
            @if ($activeFilterCount > 0)
                <span class="size-5 rounded-full bg-brand text-white text-xs font-semibold flex items-center justify-center">{{ $activeFilterCount }}</span>
            @endif
        </button>

        @foreach ($activeFilters as $filter)
            <span wire:key="filter-{{ $filter['key'] }}" class="inline-flex items-center gap-1 min-h-10 pl-3 pr-2 rounded-xl bg-brand-50 text-brand-700 text-sm font-medium shrink-0">
                {{ $filter['label'] }}
                <button
                    type="button"
                    class="size-6 rounded-full hover:bg-brand-100 flex items-center justify-center"
                    wire:click="clearFilter('{{ $filter['key'] }}')"
                    aria-label="{{ __('marketplace.listing.filters.remove_chip', ['filter' => $filter['label']]) }}"
                >
                    <x-app.icon name="close" class="size-3.5 shrink-0" />
                </button>
            </span>
        @endforeach

        {{-- Sortieren als Symbol. Die Beschriftung erscheint erst, wenn Platz
             dafuer ist. --}}
        <x-app.dropdown align="right" :label="__('portal.filters.sort')" class="shrink-0 ml-auto">
            <x-slot:trigger class="btn-ghost min-h-10 px-3">
                <x-app.icon name="sort" />
                <span class="hidden sm:inline">{{ $sortLabel }}</span>
            </x-slot:trigger>

            @foreach ($sortOptions as $value => $label)
                <button
                    type="button"
                    role="menuitem"
                    wire:key="sort-{{ $value }}"
                    wire:click="$set('sort', '{{ $value }}')"
                    @class([
                        'w-full flex items-center min-h-11 px-3 rounded-lg text-sm text-left hover:bg-zinc-100',
                        'text-brand font-semibold' => $value === $sort,
                        'text-zinc-700' => $value !== $sort,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </x-app.dropdown>
    </div>

    {{-- Filterfelder. Zugeklappt, solange nichts gesetzt ist: Auf dem Telefon
         nimmt ein offenes Panel den halben Bildschirm. --}}
    <div x-ref="filters" @if ($activeFilterCount === 0) hidden @endif class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="filter-branche" class="block text-sm text-zinc-500 mb-1">{{ __('marketplace.listing.filters.industry') }}</label>
            <select id="filter-branche" wire:model.live="industry" class="input">
                <option value="">{{ __('marketplace.listing.filters.all') }}</option>
                @foreach ($industryOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="filter-region" class="block text-sm text-zinc-500 mb-1">{{ __('marketplace.listing.filters.region') }}</label>
            <select id="filter-region" wire:model.live="region" class="input">
                <option value="">{{ __('marketplace.listing.filters.all') }}</option>
                @foreach ($regionOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="filter-preis" class="block text-sm text-zinc-500 mb-1">{{ __('marketplace.listing.filters.max_price') }}</label>
            <select id="filter-preis" wire:model.live="maxPrice" class="input">
                <option value="">{{ __('marketplace.listing.filters.all') }}</option>
                @foreach ($priceOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-end">
            <button type="button" wire:click="resetFilters" class="btn-ghost px-3" @disabled($activeFilterCount === 0)>
                {{ __('marketplace.listing.filters.reset') }}
            </button>
        </div>
    </div>

    {{-- Trefferzahl links, der Hinweis zum Kontakt rechts. Beides klein: Es
         sind Randnotizen, keine Ueberschriften. --}}
    <div class="mt-3 flex items-center justify-between text-sm text-zinc-500">
        <span>{{ trans_choice('marketplace.listing.fits_count', $resultCount, ['count' => $resultCount]) }}</span>
        <span class="flex items-center gap-1">
            <x-app.icon name="lock" class="size-3.5 shrink-0" />
            {{ __('marketplace.listing.contact_after_purchase') }}
        </span>
    </div>

    @if ($leads === [])
        <x-app.empty-state
            class="mt-2"
            icon="cart"
            :title="__('marketplace.listing.empty')"
            :description="__('marketplace.listing.empty_hint')"
        >
            @if ($activeFilterCount > 0)
                <x-app.button variant="secondary" wire:click="resetFilters">
                    {{ __('marketplace.listing.filters.reset') }}
                </x-app.button>
            @endif
        </x-app.empty-state>
    @else
        <section class="card overflow-hidden mt-2">
            <ul class="divide-y divide-zinc-200">
                @foreach ($leads as $lead)
                    <li wire:key="lead-{{ $lead['id'] }}">
                        <div class="relative flex items-start gap-3 px-4 py-3 hover:bg-zinc-50 transition-colors">
                            <div class="flex-1 min-w-0">
                                {{-- Der Name traegt die Flaeche der ganzen Zeile
                                     (after:inset-0). Ein Antippen irgendwo in
                                     der Zeile oeffnet damit das Blatt, ohne
                                     dass ein zweiter Knopf darueber liegt. --}}
                                <div class="flex items-center justify-between gap-2">
                                    <button
                                        type="button"
                                        class="font-semibold text-zinc-900 truncate text-left after:absolute after:inset-0"
                                        wire:click="openLead({{ $lead['id'] }})"
                                    >{{ $lead['name'] }}</button>

                                    <span class="text-xs text-zinc-500 shrink-0 flex items-center gap-2">
                                        @if ($lead['is_new'])
                                            <span class="pill-solid text-xs py-0.5">{{ __('marketplace.listing.badge_new') }}</span>
                                        @endif
                                        <span title="{{ $lead['created_at_exact'] }}">{{ $lead['created_at'] }}</span>
                                    </span>
                                </div>

                                <p class="text-sm text-zinc-500 truncate flex items-center gap-1">
                                    <x-app.icon name="pin" class="size-3.5 text-zinc-400 shrink-0" />
                                    {{ $lead['postal_code'] }}
                                </p>

                                <div class="mt-1.5 flex items-center justify-between gap-3">
                                    <div class="flex gap-1 overflow-x-auto no-scrollbar min-w-0">
                                        @foreach ($lead['chips'] as $chip)
                                            <span class="text-xs px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-700 whitespace-nowrap">{{ $chip }}</span>
                                        @endforeach
                                    </div>

                                    {{-- Preis und Kaufknopf liegen ueber der
                                         Zeilenflaeche (z-10), sonst oeffnete der
                                         Kauf nur das Blatt. --}}
                                    <span class="relative z-10 flex items-center gap-2 shrink-0">
                                        <span class="text-sm font-semibold text-zinc-900 tabular-nums"
                                            @if ($surchargeHint !== null) title="{{ $surchargeHint }}" @endif
                                        >{{ $lead['price'] }}</span>
                                        <button
                                            type="button"
                                            class="btn-primary min-h-10 px-3.5"
                                            wire:click="openLead({{ $lead['id'] }})"
                                            @disabled(! $lead['purchasable'])
                                        >
                                            <x-app.icon name="cart" class="size-4 shrink-0" />
                                            <span class="sr-only sm:not-sr-only">{{ __('marketplace.listing.purchase_short') }}</span>
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($remaining > 0)
                <div class="px-4 py-3 border-t border-zinc-200 text-center">
                    <button type="button" wire:click="loadMore" class="btn-secondary w-full sm:w-auto">
                        {{ trans_choice('marketplace.listing.load_more', $nextBatch, ['count' => $nextBatch]) }}
                    </button>
                </div>
            @endif
        </section>

        <p class="mt-4 text-xs text-zinc-500 text-center">{{ __('marketplace.listing.auto_top') }}</p>
    @endif

    {{-- Das Blatt. Auf dem Telefon von unten, ab sm mittig. Geschlossen wird
         ueber die Flaeche dahinter, den Knopf oder Escape (portal.js). --}}
    @if ($sheet !== null)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center" data-dialog>
            <div class="absolute inset-0 bg-zinc-900/40" wire:click="closeLead" aria-hidden="true"></div>

            <div
                class="relative bg-white rounded-t-2xl sm:rounded-2xl border border-zinc-200 shadow-lg w-full sm:max-w-md max-h-[85vh] overflow-y-auto"
                role="dialog"
                aria-modal="true"
                aria-labelledby="sheet-title"
            >
                {{-- Der Griff. Auf dem Telefon sagt er, dass sich das Blatt
                     wegschieben laesst. --}}
                <div class="sm:hidden pt-2 flex justify-center">
                    <span class="h-1.5 w-10 rounded-full bg-zinc-300"></span>
                </div>

                <div class="p-5 space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 id="sheet-title" class="text-lg font-semibold text-zinc-900">{{ $sheet['name'] }}</h2>
                            <p class="text-sm text-zinc-500">{{ $sheet['meta'] }}</p>
                        </div>
                        @if ($sheet['is_new'])
                            <span class="pill-solid">{{ __('marketplace.listing.badge_new') }}</span>
                        @endif
                    </div>

                    @if ($sheet['attributes'] !== [])
                        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                            @foreach ($sheet['attributes'] as $attribute)
                                <dt class="text-zinc-500">{{ $attribute['label'] }}</dt>
                                <dd class="text-zinc-900 font-medium">{{ $attribute['value'] }}</dd>
                            @endforeach
                        </dl>
                    @endif

                    @if ($sheet['free_text'] !== null)
                        <p class="text-sm text-zinc-700 border-l-2 border-brand pl-3">„{{ $sheet['free_text'] }}"</p>
                    @endif

                    <p class="text-xs text-zinc-500 flex items-center gap-1.5">
                        <x-app.icon name="lock" class="size-3.5 shrink-0" />
                        {{ __('marketplace.listing.sheet_hint') }}
                    </p>

                    {{-- Der Gesamtpreis, den dieser Kaeufer traegt: bei Pay as
                         you go einschliesslich Aufschlag (LP-POSTPAID-007). --}}
                    <div class="flex items-center justify-between gap-3 pt-1 border-t border-zinc-200">
                        <span class="text-sm text-zinc-500">{{ __('portal.topup.total') }}</span>
                        <span class="text-lg font-semibold text-zinc-900 tabular-nums">{{ $sheet['price'] }}</span>
                    </div>

                    @if ($surchargeHint !== null)
                        <p class="text-xs text-zinc-500 flex items-center gap-1.5 -mt-1">
                            <x-app.icon name="info" class="size-3.5 shrink-0" />
                            {{ $surchargeHint }}
                        </p>
                    @endif

                    <div class="flex gap-2 pt-1">
                        <button type="button" class="btn-ghost flex-1" wire:click="closeLead">
                            {{ __('portal.close') }}
                        </button>

                        <button
                            type="button"
                            class="btn-primary flex-1"
                            wire:click="purchase({{ $sheet['id'] }}, {{ $sheet['price_cents'] }})"
                            wire:loading.attr="disabled"
                            @disabled(! $sheet['purchasable'] || ! $sheet['affordable'])
                        >
                            <x-app.icon name="cart" />
                            {{ __('marketplace.listing.purchase_now') }}
                        </button>
                    </div>

                    @if ($blocked)
                        <p class="text-xs text-red-600">{{ __('portal.postpaid.blocked.heading') }}</p>
                    @elseif ($sheet['purchasable'] && ! $sheet['affordable'])
                        <p class="text-xs text-red-600">
                            {{ __('marketplace.listing.price_exceeds_balance') }}
                            <a href="{{ $topUpUrl }}" class="underline">{{ __('marketplace.wallet.top_up.title') }}</a>
                        </p>
                    @elseif (! $sheet['purchasable'])
                        <p class="text-xs text-zinc-500">{{ __('marketplace.listing.purchase_unavailable') }}</p>
                    @endif
                </div>
            </div>
        </div>
    @endif

</div>
