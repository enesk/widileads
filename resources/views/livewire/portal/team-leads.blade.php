{{--
    "Team Leads" im Portal -- alle Leadkaeufe des Mandanten (Ticket #3).

    Nach der Gestaltungsvorgabe aus #4. Eine Auswertung, keine Arbeitsliste:
    Kopf, drei Kennzahlen, Filterzeile, Liste, Blaetterleiste.

    **Die Zeile ist nicht anklickbar** -- kein hover:bg-zinc-50, kein
    after:inset-0, kein Chevron. Im Portal verspricht ein Hover-Hintergrund in
    einer Liste ein Ziel; hier gibt es keines, und ein Klickversprechen ohne
    Ziel liest sich als Fehler.

    Kopfzeile und Zeile tragen dasselbe Raster, sonst laufen Spaltenkoepfe und
    Werte auseinander.

    Kontaktdaten kommen fertig maskiert aus dem LeadPresenter. Hier wird nichts
    verdeckt und nichts entschieden.

    Erwartete Daten:
      $stats          count, spent, top_buyer
      $buyerOptions   Wert => Beschriftung
      $periodOptions  Wert => Beschriftung
      $statusOptions  Wert => Beschriftung
      $rows           date, name, funnel, funnel_icon, postal_code, price,
                      badge, phone_masked, buyer
      $purchases      Paginator
      $hasAnyPurchase Hat der Mandant ueberhaupt schon gekauft?
      $workspaceName  Name des Mandanten fuer die Unterzeile
      $marketplaceUrl Weg zum Marktplatz
--}}
<div>

    {{-- Kopf: worum es geht, und warum die Nummern fehlen. Der zweite Satz
         nimmt der Seite die Frage "ist das kaputt?". --}}
    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div>
            <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">
                {{ __('marketplace.team_leads.heading') }}
            </h1>
            <p class="text-zinc-500 mt-1 max-w-prose">
                {{ __('marketplace.team_leads.subtitle', ['workspace' => $workspaceName]) }}
            </p>
        </div>

        <div class="flex gap-2 shrink-0">
            <button type="button" class="btn-secondary min-h-11 px-3.5" wire:click="exportCsv" aria-label="{{ __('marketplace.team_leads.export') }}">
                <x-app.icon name="download" />
                <span class="hidden sm:inline">CSV</span>
            </button>
        </div>
    </div>

    {{-- Drei Zahlen, immer zum gewaehlten Zeitraum. Das steht in der
         Beschriftung, sonst widersprechen Kennzahl und Liste einander. --}}
    <div class="mt-4 grid grid-cols-3 gap-2 sm:gap-4 transition-opacity" wire:loading.class="opacity-60 pointer-events-none">
        <div class="card px-3 py-3 sm:px-5 sm:py-4 min-w-0">
            <p class="text-xs sm:text-sm text-zinc-500 truncate">{{ __('marketplace.team_leads.stats.count') }}</p>
            <p class="font-semibold text-zinc-900 tabular-nums text-base sm:text-2xl whitespace-nowrap">{{ $stats['count'] }}</p>
        </div>

        <div class="card px-3 py-3 sm:px-5 sm:py-4 min-w-0">
            <p class="text-xs sm:text-sm text-zinc-500 truncate">{{ __('marketplace.team_leads.stats.spent') }}</p>
            <p class="font-semibold text-zinc-900 tabular-nums text-base sm:text-2xl whitespace-nowrap">{{ $stats['spent'] }}</p>
        </div>

        <div class="card px-3 py-3 sm:px-5 sm:py-4 min-w-0">
            <p class="text-xs sm:text-sm text-zinc-500 truncate">{{ __('marketplace.team_leads.stats.top_buyer') }}</p>
            <p class="font-semibold text-zinc-900 text-base truncate" title="{{ $stats['top_buyer'] }}">{{ $stats['top_buyer'] }}</p>
        </div>
    </div>

    {{-- Links die Zahl der gezeigten Zeilen, rechts die drei Filter. Keine
         Freitextsuche: Eine Suche ueber verdeckte Felder waere halb blind. --}}
    <x-app.filter-bar class="mt-4" :summary="__('marketplace.team_leads.shown', ['count' => $purchases->count(), 'total' => $purchases->total()])">
        <div class="relative flex-1 min-w-0">
            <label for="team-buyer" class="sr-only">{{ __('marketplace.team_leads.filter.buyer') }}</label>
            <select id="team-buyer" wire:model.live="buyer" class="input appearance-none pr-9 min-h-11">
                @foreach ($buyerOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <span class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                <x-app.icon name="chevron-down" class="size-4 text-zinc-400 shrink-0" />
            </span>
        </div>

        <div class="relative flex-1 min-w-0">
            <label for="team-period" class="sr-only">{{ __('marketplace.team_leads.filter.period_label') }}</label>
            <select id="team-period" wire:model.live="period" class="input appearance-none pr-9 min-h-11">
                @foreach ($periodOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <span class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                <x-app.icon name="chevron-down" class="size-4 text-zinc-400 shrink-0" />
            </span>
        </div>

        <div class="relative flex-1 min-w-0">
            <label for="team-status" class="sr-only">{{ __('marketplace.team_leads.filter.status') }}</label>
            <select id="team-status" wire:model.live="status" class="input appearance-none pr-9 min-h-11">
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <span class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                <x-app.icon name="chevron-down" class="size-4 text-zinc-400 shrink-0" />
            </span>
        </div>
    </x-app.filter-bar>

    @if ($rows === [])
        @if ($hasAnyPurchase)
            {{-- Im Zeitraum nichts: Der Weg heraus ist der Filter, nicht der
                 Marktplatz. --}}
            <x-app.empty-state
                class="mt-4"
                icon="users"
                :title="__('marketplace.team_leads.empty.filtered.title')"
                :description="__('marketplace.team_leads.empty.filtered.text')"
            >
                <x-app.button variant="secondary" wire:click="resetFilters">
                    {{ __('marketplace.team_leads.empty.filtered.action') }}
                </x-app.button>
            </x-app.empty-state>
        @else
            {{-- Noch nie gekauft: Hier gibt es keinen Filter
                 zurueckzusetzen. --}}
            <x-app.empty-state
                class="mt-4"
                icon="leads"
                :title="__('marketplace.team_leads.empty.never.title')"
                :description="__('marketplace.team_leads.empty.never.text')"
            >
                <x-app.button variant="primary" :href="$marketplaceUrl">
                    {{ __('marketplace.purchased.to_marketplace') }}
                </x-app.button>
            </x-app.empty-state>
        @endif
    @else
        <div class="mt-4 card overflow-hidden transition-opacity" wire:loading.class="opacity-60 pointer-events-none">
            {{-- Spaltenkoepfe erst ab md. Darunter ist jede Zeile ein Block,
                 eine Tabelle waere dort nur waagerechtes Scrollen. --}}
            <div class="hidden md:grid px-4 py-2.5 border-b border-zinc-200 bg-zinc-50/60 grid-cols-[7rem_1fr_10rem_7rem] gap-x-3 items-center">
                <span class="text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ __('marketplace.team_leads.columns.date') }}</span>
                <span class="text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ __('marketplace.team_leads.columns.lead') }}</span>
                <span class="text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ __('marketplace.team_leads.columns.buyer') }}</span>
                <span class="text-xs font-semibold uppercase tracking-wide text-zinc-400 text-right">{{ __('marketplace.team_leads.columns.price') }}</span>
            </div>

            <ul class="divide-y divide-zinc-200">
                @foreach ($rows as $row)
                    <li wire:key="team-purchase-{{ $row['id'] }}">
                        <div class="px-4 py-3 grid grid-cols-[1fr_auto] md:grid-cols-[7rem_1fr_10rem_7rem] gap-x-3 gap-y-1 items-center">
                            <p class="text-xs md:text-sm text-zinc-500 tabular-nums">{{ $row['date'] }}</p>

                            <p class="font-semibold text-zinc-900 tabular-nums text-right whitespace-nowrap md:order-4">{{ $row['price'] }}</p>

                            <div class="col-span-2 md:col-span-1 md:order-2 min-w-0">
                                <p class="font-semibold text-zinc-900 truncate">{{ $row['name'] }}</p>

                                <div class="mt-0.5 flex items-center gap-2 flex-wrap text-sm text-zinc-500">
                                    <span @class([
                                        'pill bg-amber-50 text-amber-800' => $row['badge']['tone'] === 'amber',
                                        'pill bg-emerald-50 text-emerald-700' => $row['badge']['tone'] === 'emerald',
                                        'pill' => $row['badge']['tone'] === 'neutral',
                                    ])>
                                        <x-app.icon :name="$row['badge']['icon']" class="size-3.5 shrink-0" />
                                        {{ $row['badge']['label'] }}
                                    </span>

                                    <span class="pill">
                                        <x-app.icon :name="$row['funnel_icon']" class="size-3.5 shrink-0" />
                                        {{ $row['funnel'] }}
                                    </span>

                                    <span class="truncate">{{ $row['postal_code'] }}</span>
                                </div>

                                {{-- Einmal je Zeile: die verdeckte Nummer mit
                                     dem Grund daneben. Kein Aufdecken, kein
                                     title mit dem Klartext. --}}
                                <p class="mt-1 text-xs text-zinc-400 flex items-center gap-1.5">
                                    <x-app.icon name="lock" class="size-3.5 shrink-0" />
                                    <span class="tabular-nums">{{ $row['phone_masked'] }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span>{{ __('marketplace.team_leads.masked_hint') }}</span>
                                </p>
                            </div>

                            <div class="col-span-2 md:col-span-1 md:order-3 min-w-0 flex items-center gap-2">
                                @if ($row['buyer']['automatic'])
                                    {{-- Symbol und Wort tragen die Bedeutung,
                                         nicht die Farbe. --}}
                                    <span class="pill">
                                        <x-app.icon name="zap" class="size-3.5 shrink-0" />
                                        {{ $row['buyer']['name'] }}
                                    </span>
                                @else
                                    <span @class([
                                        'size-7 rounded-full text-xs font-semibold flex items-center justify-center shrink-0',
                                        'bg-brand text-white' => $row['buyer']['is_self'],
                                        'bg-zinc-200 text-zinc-600' => ! $row['buyer']['is_self'],
                                    ]) aria-hidden="true">{{ $row['buyer']['initials'] }}</span>

                                    <span class="text-sm text-zinc-700 truncate">
                                        {{ $row['buyer']['name'] }}
                                        @if ($row['buyer']['is_self'])
                                            {{ __('marketplace.team_leads.buyer.self') }}
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Der Hinweis gilt fuer die ganze Liste und steht deshalb einmal
             darunter, nicht in jeder Zeile. --}}
        <p class="mt-4 text-sm text-zinc-500 flex items-center justify-center gap-2 text-center">
            <x-app.icon name="info" class="size-4 text-zinc-400 shrink-0" />
            <span>{{ __('marketplace.team_leads.contact_note') }}</span>
        </p>

        <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-zinc-500">
                {{ __('marketplace.team_leads.shown', ['count' => $purchases->count(), 'total' => $purchases->total()]) }}
            </p>

            <x-app.pagination :paginator="$purchases" :wire="true" />
        </div>
    @endif

</div>
