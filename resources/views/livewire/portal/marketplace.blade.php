{{--
    Der Marktplatz im Portal, Fassung "Leads nach Funnel" (Entwurf
    `widileads-funnel-layout.html`).

    Erste Ebene sind die Funnel-Reiter: Ein Kaeufer denkt in Gewerken
    (Elektriker, Tierversicherung), nicht in einer einzigen langen Liste. Jede
    Karte sagt, aus welchem Funnel und von welcher Seite die Anfrage kam und
    fuer welches Firmenprofil. Unter "Alle Funnels" laesst sich die Liste nach
    Funnel gruppieren.

    Masse stehen in Pixeln wie im Entwurf und nicht in rem: Der Entwurf rechnet
    mit 18 Pixeln Grundschrift, das Portal mit 20. In rem waere jedes Mass um
    ein Neuntel groesser ausgefallen.

    Aeltere Fassung: Listenfassung (Entwurf `marktplatz-v2.html`).

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
      $funnelTabs        id, name, icon, count, active -- erster Reiter "Alle"
      $groups            nach Funnel gruppierte Karten, oder null fuer die Liste
--}}
<div class="text-[18px] leading-normal">

    {{-- Kopf: Titel und die Guthabenkachel. --}}
    <div class="flex flex-wrap items-center justify-between gap-[16px] mb-[24px]">
        <h1 class="m-0 text-[36px] font-bold tracking-[-0.02em] text-zinc-900 leading-tight">
            {{ __('marketplace.listing.heading') }}
        </h1>

        <div class="flex items-center gap-[10px] rounded-[16px] border border-zinc-200 bg-white px-[14px] py-[10px] text-zinc-500">
            {{ __('portal.balance') }}
            <b class="text-[19.8px] font-bold text-zinc-900 tabular-nums">{{ $balance }}</b>
            <a href="{{ $topUpUrl }}" class="grid size-[32px] place-items-center rounded-[8px] bg-zinc-100 text-zinc-900" aria-label="{{ __('marketplace.wallet.top_up.title') }}">
                <x-app.icon name="plus" class="size-[22.5px]" />
            </a>
        </div>
    </div>

    {{-- Rueckmeldung des letzten Kaufversuchs. Bleibt ein Band und wird kein
         Toast: Sie traegt den Weg zur Aufladung, und der waere nach fuenf
         Sekunden weg. --}}
    @if ($message !== null)
        <div
            role="status"
            @class([
                'mb-5 flex flex-wrap items-center gap-3 rounded-xl border p-4 text-sm',
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
        <div class="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <span class="flex-1 min-w-0">
                {{ $postpaid ? __('portal.postpaid.balance.exhausted') : __('marketplace.listing.no_funds') }}
            </span>
            <a href="{{ $topUpUrl }}" class="btn-secondary shrink-0">{{ __('portal.top_up') }}</a>
        </div>
    @endunless

    {{-- Erste Ebene: die Funnels. --}}
    <div class="mb-[20px] flex flex-wrap gap-[8px]" role="group" aria-label="{{ __('marketplace.listing.funnels.label') }}">
        @foreach ($funnelTabs as $tab)
            <button
                type="button"
                wire:key="funnel-{{ $tab['id'] === '' ? 'alle' : $tab['id'] }}"
                wire:click="setFunnel('{{ $tab['id'] }}')"
                aria-pressed="{{ $tab['active'] ? 'true' : 'false' }}"
                @class([
                    'flex min-h-[56px] items-center gap-[12px] rounded-[16px] border px-[16px] py-[12px] text-left transition-colors max-[900px]:flex-[1_1_45%]',
                    'border-brand bg-brand text-white' => $tab['active'],
                    'border-zinc-200 bg-white text-zinc-900 hover:border-zinc-400' => ! $tab['active'],
                ])
            >
                <span @class([
                    'grid size-[32px] shrink-0 place-items-center rounded-[10px]',
                    'bg-white/15 text-white' => $tab['active'],
                    'bg-zinc-100 text-zinc-700' => ! $tab['active'],
                ])>
                    <x-app.icon :name="$tab['icon']" class="size-[19.8px]" />
                </span>
                <span>
                    <b class="block font-semibold leading-[1.2]">{{ $tab['name'] }}</b>
                    <small @class(['block text-[14.4px]', 'text-white/75' => $tab['active'], 'text-zinc-500' => ! $tab['active']])>
                        {{ trans_choice('marketplace.listing.funnels.count', $tab['count'], ['count' => $tab['count']]) }}
                    </small>
                </span>
            </button>
        @endforeach
    </div>

    {{-- Zweite Ebene: Filter, Sortierung, Zahl, Darstellung. --}}
    <div class="mb-[12px] flex flex-wrap items-center gap-[12px]">
        <button type="button" class="inline-flex min-h-[44px] items-center gap-[8px] whitespace-nowrap rounded-[12px] border border-zinc-200 bg-white px-[16px] py-[10px] text-zinc-900 transition-colors hover:border-zinc-400" x-data x-on:click="$refs.filters.hidden = ! $refs.filters.hidden">
            <x-app.icon name="filter" class="size-[22.5px]" />
            {{ __('portal.filters.label') }}
            @if ($activeFilterCount > 0)
                <span class="grid size-[20px] place-items-center rounded-full bg-brand text-[13px] font-semibold text-white">{{ $activeFilterCount }}</span>
            @endif
        </button>

        <x-app.dropdown align="left" :label="__('portal.filters.sort')">
            <x-slot:trigger class="inline-flex min-h-[44px] items-center gap-[8px] whitespace-nowrap rounded-[12px] border border-zinc-200 bg-white px-[16px] py-[10px] text-zinc-900 transition-colors hover:border-zinc-400">
                <x-app.icon name="sort" class="size-[22.5px]" />
                {{ $sortLabel }}
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

        <span class="ml-[4px] text-[16.2px] text-zinc-500">
            {{ trans_choice('marketplace.listing.fits_count', $resultCount, ['count' => $resultCount]) }}
        </span>

        {{-- Darstellung. Nach Funnel gruppiert wird nur unter "Alle Funnels". --}}
        <div class="ml-auto inline-flex overflow-hidden rounded-[12px] border border-zinc-200 bg-white max-[900px]:ml-0" role="group" aria-label="{{ __('marketplace.listing.view.label') }}">
            <button
                type="button"
                wire:click="setView('liste')"
                aria-pressed="{{ $view === 'liste' ? 'true' : 'false' }}"
                @class(['flex min-h-[44px] items-center gap-[8px] px-[14px] py-[10px]', 'bg-brand-50 font-medium text-brand' => $view === 'liste', 'text-zinc-500' => $view !== 'liste'])
            >
                <x-app.icon name="list" class="size-[22.5px]" />
                {{ __('marketplace.listing.view.list') }}
            </button>
            <button
                type="button"
                wire:click="setView('funnel')"
                aria-pressed="{{ $view === 'funnel' ? 'true' : 'false' }}"
                @class(['flex min-h-[44px] items-center gap-[8px] px-[14px] py-[10px]', 'bg-brand-50 font-medium text-brand' => $view === 'funnel', 'text-zinc-500' => $view !== 'funnel'])
            >
                <x-app.icon name="rows" class="size-[22.5px]" />
                {{ __('marketplace.listing.view.by_funnel') }}
            </button>
        </div>
    </div>

    {{-- Filterfelder. Zugeklappt, solange nichts gesetzt ist. Der Funnel steht
         oben als Reiter und fehlt deshalb hier. --}}
    <div x-ref="filters" @if ($activeFilterCount === 0) hidden @endif class="mb-[12px] grid gap-[12px] sm:grid-cols-3">
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

    <p class="mb-[12px] flex items-center gap-[6px] text-[16.2px] text-zinc-500">
        <x-app.icon name="lock" class="size-[18px]" />
        {{ __('marketplace.listing.contact_after_purchase_sentence') }}
    </p>

    @if ($leads === [])
        {{-- Leerer Zustand: gestrichelt, damit er nicht wie eine Karte aussieht. --}}
        <div class="rounded-[16px] border border-dashed border-zinc-300 bg-white p-[40px] text-center text-zinc-500">
            <b class="mb-[6px] block text-[19.8px] text-zinc-900">
                {{ $funnelTabs[0]['active'] ?? true ? __('marketplace.listing.empty') : __('marketplace.listing.funnels.empty_title') }}
            </b>
            {{ $funnelTabs[0]['active'] ?? true ? __('marketplace.listing.empty_hint') : __('marketplace.listing.funnels.empty_text') }}
        </div>
    @else
        @if ($groups !== null)
            @foreach ($groups as $group)
                <div wire:key="group-{{ $group['id'] }}">
                    <h2 @class(['mb-[12px] flex items-center gap-[10px] text-[20.7px] font-semibold text-zinc-900', 'mt-[8px]' => $loop->first, 'mt-[28px]' => ! $loop->first])>
                        <span class="grid size-[28px] place-items-center rounded-[8px] bg-brand-50 text-brand">
                            <x-app.icon :name="$group['icon']" class="size-[18px]" />
                        </span>
                        {{ $group['name'] }}
                        <span class="text-[17.1px] font-normal text-zinc-500">{{ $group['count'] }}</span>
                    </h2>

                    <div class="flex flex-col gap-[12px]">
                        @foreach ($group['cards'] as $lead)
                            @include('livewire.portal.partials.marketplace-card', ['lead' => $lead])
                        @endforeach
                    </div>
                </div>
            @endforeach
        @else
            <div class="flex flex-col gap-[12px]">
                @foreach ($leads as $lead)
                    @include('livewire.portal.partials.marketplace-card', ['lead' => $lead])
                @endforeach
            </div>
        @endif

        @if ($remaining > 0)
            <div class="mt-[16px] text-center">
                <button type="button" wire:click="loadMore" class="inline-flex min-h-[44px] items-center gap-[8px] whitespace-nowrap rounded-[12px] border border-zinc-200 bg-white px-[16px] py-[10px] text-zinc-900 hover:border-zinc-400">
                    {{ trans_choice('marketplace.listing.load_more', $nextBatch, ['count' => $nextBatch]) }}
                </button>
            </div>
        @endif
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
                        <x-app.attribute-list :items="$sheet['attributes']" />
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
