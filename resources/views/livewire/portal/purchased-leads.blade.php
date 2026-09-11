{{--
    "Meine Leads" im Portal, Listenfassung (Entwurf `meine-leads-liste.html`).

    Loest die Kartenfassung ab. Der Grund steht im Entwurf: Sechs gleich
    aussehende Kacheln beantworten nicht die Frage, mit der ein Kaeufer diese
    Seite oeffnet -- wen muss ich heute anrufen, damit er mich nichts kostet.
    Eine Zeile je Lead, gruppiert nach Dringlichkeit, beantwortet sie.

    Zwei Dinge sind aus der Liste heraus und stehen nur noch auf der
    Detailseite: Rufnummer und E-Mail. Der Hinweis zur verdeckten Nummer steht
    dafuer genau einmal ueber der Liste statt sechsmal darin.

    Die Reiter filtern serverseitig, nicht per Skript am fertigen HTML: Die
    Liste ist geblaettert, ein Ausblenden im Browser haette nur die Zeilen der
    aktuellen Seite getroffen und die Zaehler daneben zu einer Luege gemacht.
    Aus demselben Grund entstehen die Gruppen im Server aus den Zeilen dieser
    Seite.

    Diese Ansicht entscheidet nichts. Sie ruft keinen Dienst auf und rechnet
    nicht -- alle Werte liegen fertig vor, der Name so, wie der LeadPresenter
    ihn geliefert hat.

    Erwartete Daten:
      $groups          key, title, hint, urgent, rows
      $rows            alle Zeilen dieser Seite (fuer den leeren Zustand)
      $purchases       Paginator (Gesamtzahl und Weiterblaettern)
      $tabs            key, label, count, active
      $sortOptions     Sortierungen
      $deadlineNotice  count, name, id -- oder null
      $emptyText       title, text je Reiter
      $marketplaceUrl  Weg zum Marktplatz
--}}
<div>

    {{-- Kopf: worum es geht, und die zwei Wege hinaus. --}}
    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div>
            <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">
                {{ __('marketplace.purchased.heading') }}
            </h1>
            <p class="text-zinc-500 mt-1 max-w-prose">
                {{ __('marketplace.purchased.subtitle') }}
            </p>
        </div>

        {{-- Reine Symbolknoepfe. Der Text kommt erst, wenn Platz dafuer ist
             -- auf dem Telefon frisst "Zum Marktplatz" die halbe Kopfzeile. --}}
        <div class="flex gap-2 shrink-0">
            <button type="button" class="btn-secondary min-h-11 px-3.5" wire:click="exportCsv" aria-label="{{ __('marketplace.purchased.export') }}">
                <x-app.icon name="download" />
                <span class="hidden sm:inline">CSV</span>
            </button>
            <a href="{{ $marketplaceUrl }}" class="btn-primary min-h-11 px-3.5" aria-label="{{ __('marketplace.purchased.to_marketplace') }}">
                <x-app.icon name="cart" />
                <span class="hidden sm:inline">{{ __('marketplace.purchased.to_marketplace') }}</span>
            </a>
        </div>
    </div>

    {{-- Eine Frist, die heute endet. Wer sie verstreichen laesst, zahlt fuer
         einen Lead, den er nie gesprochen hat. --}}
    @if ($deadlineNotice !== null)
        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 flex flex-col sm:flex-row sm:items-center gap-3">
            <x-app.icon name="alert" class="size-5 text-amber-500 shrink-0" />
            <p class="text-sm text-amber-900 flex-1">
                <span class="font-semibold">
                    {{ trans_choice('marketplace.purchased.deadline_notice.count', $deadlineNotice['count'], ['count' => $deadlineNotice['count']]) }}
                </span>
                {{ __('marketplace.purchased.deadline_notice.text', ['name' => $deadlineNotice['name']]) }}
            </p>
            <a href="#lead-{{ $deadlineNotice['id'] }}" class="btn-primary shrink-0" data-scroll-to="lead-{{ $deadlineNotice['id'] }}">
                <x-app.icon name="phone" />
                {{ __('call.panel.action') }}
            </a>
        </div>
    @endif

    {{-- Reiter in einer waagerecht scrollbaren Zeile. Der aktive Reiter
         schiebt sich in die Mitte (portal.js), damit er auf dem Telefon nicht
         am Rand klebt. --}}
    <div class="mt-4 -mx-4 px-4 overflow-x-auto no-scrollbar" data-tabstrip>
        <div class="flex gap-1 p-1 rounded-xl bg-zinc-100 w-max" role="tablist" aria-label="{{ __('marketplace.purchased.tabs.label') }}">
            @foreach ($tabs as $tab)
                <button
                    type="button"
                    role="tab"
                    wire:key="tab-{{ $tab['key'] }}"
                    wire:click="setStatus('{{ $tab['key'] }}')"
                    aria-selected="{{ $tab['active'] ? 'true' : 'false' }}"
                    @class(['tab shrink-0', 'tab-active' => $tab['active']])
                >
                    {{ $tab['label'] }}
                    <span class="tab-count">{{ $tab['count'] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Suche und Sortieren in einer Zeile: Die Suche nimmt den Platz, das
         Sortieren ein Symbol. --}}
    <div class="mt-3 flex items-center gap-2">
        <div class="relative flex-1 min-w-0">
            <label for="leads-search" class="sr-only">{{ __('marketplace.purchased.search') }}</label>
            <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">
                <x-app.icon name="search" class="size-5 text-zinc-400 shrink-0" />
            </span>
            <input
                id="leads-search"
                type="search"
                class="input pl-10"
                placeholder="{{ __('marketplace.purchased.search_placeholder') }}"
                wire:model.live.debounce.400ms="search"
            >
        </div>

        <x-app.dropdown align="right" :label="__('portal.filters.sort')" class="shrink-0">
            <x-slot:trigger class="btn-secondary min-h-11 px-3.5">
                <x-app.icon name="sort" />
                <span class="hidden sm:inline">{{ $sortOptions[$sort] ?? '' }}</span>
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

    @if ($rows === [])
        <x-app.empty-state
            class="mt-4"
            icon="leads"
            :title="$emptyText['title']"
            :description="$emptyText['text']"
        >
            <x-app.button variant="primary" :href="$marketplaceUrl">
                {{ __('marketplace.purchased.to_marketplace') }}
            </x-app.button>
        </x-app.empty-state>
    @else
        <div class="mt-4 space-y-4">
            @foreach ($groups as $group)
                <section wire:key="group-{{ $group['key'] }}" @class(['card overflow-hidden', 'border-amber-200' => $group['urgent']])>
                    <div class="px-4 py-3 border-b border-zinc-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-3 bg-zinc-50/60">
                        <h2 class="font-semibold text-zinc-900">{{ $group['title'] }}</h2>
                        <span class="text-sm text-zinc-500">{{ $group['hint'] }}</span>
                    </div>

                    <ul class="divide-y divide-zinc-200">
                        @foreach ($group['rows'] as $row)
                            <li wire:key="purchase-{{ $row['id'] }}" id="lead-{{ $row['id'] }}">
                                {{-- Die ganze Zeile ist tippbar: Der Verweis auf
                                     dem Namen spannt sich mit after:inset-0
                                     ueber die Flaeche. Ein Chevron daneben
                                     waere ein zweites Ziel fuer dasselbe. --}}
                                <div class="relative flex items-start gap-3 px-4 py-3 hover:bg-zinc-50 transition-colors">
                                    <span @class([
                                        'size-10 rounded-full text-sm font-semibold flex items-center justify-center shrink-0',
                                        'bg-brand text-white' => $row['avatar_tone'] === 'brand',
                                        'bg-emerald-600 text-white' => $row['avatar_tone'] === 'emerald',
                                        'bg-zinc-200 text-zinc-600' => $row['avatar_tone'] === 'neutral',
                                    ]) aria-hidden="true">{{ $row['initials'] }}</span>

                                    <div class="flex-1 min-w-0">
                                        {{-- Pille unter den Namen: Ein langer
                                             Name wuerde daneben abgeschnitten. --}}
                                        <a href="{{ $row['url'] }}" class="font-semibold text-zinc-900 truncate block after:absolute after:inset-0">{{ $row['name'] }}</a>

                                        <div class="mt-0.5 flex items-center gap-2 flex-wrap">
                                            <span @class([
                                                'pill-brand' => $row['badge']['tone'] === 'brand',
                                                'pill bg-amber-50 text-amber-800' => $row['badge']['tone'] === 'amber',
                                                'pill bg-emerald-50 text-emerald-700' => $row['badge']['tone'] === 'emerald',
                                                'pill' => $row['badge']['tone'] === 'neutral',
                                            ])>
                                                <x-app.icon :name="$row['badge']['icon']" class="size-3.5 shrink-0" />
                                                {{ $row['badge']['label'] }}
                                            </span>
                                            <span class="text-sm text-zinc-500 truncate">{{ $row['postal_code'] }}</span>
                                        </div>

                                        @if ($row['chips'] !== [])
                                            <div class="mt-1.5 flex gap-1 overflow-x-auto no-scrollbar min-w-0">
                                                @foreach ($row['chips'] as $chip)
                                                    <span class="text-xs px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-700 whitespace-nowrap">{{ $chip }}</span>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Fortschritt links, Handlung rechts
                                             ueber die Restbreite. Der Knopf
                                             liegt ueber der Zeilenflaeche
                                             (z-10), sonst oeffnete er nur die
                                             Detailseite. --}}
                                        <div class="mt-2 flex items-center gap-3">
                                            @if ($row['settlement'] !== null)
                                                <span class="text-xs text-zinc-500">{{ $row['settlement'] }}</span>
                                            @else
                                                <x-app.attempt-bars :done="$row['attempts_done']" :total="$row['attempts_total']" class="shrink-0" />
                                            @endif

                                            <span class="relative z-10 flex-1 min-w-0 flex">
                                                <x-app.lead-row-action :row="$row" class="w-full sm:w-auto sm:ml-auto" />
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        {{-- Zahl und Weg zur naechsten Seite, wie im Entwurf eine Zeile statt
             einer Blaetterleiste. --}}
        {{-- Der Hinweis zur verdeckten Nummer: einmal, ganz unten. Er gilt
             fuer die ganze Liste und ist keine Ueberschrift. --}}
        <p class="mt-6 text-sm text-zinc-500 flex items-center justify-center gap-2">
            <x-app.icon name="info" class="size-4 text-zinc-400 shrink-0" />
            {{ __('marketplace.purchased.masking_hint') }}
        </p>

        <p class="mt-2 text-sm text-zinc-500 text-center">
            {{ trans_choice('marketplace.purchased.count_line', $purchases->count(), [
                'count' => $purchases->count(),
                'total' => $purchases->total(),
            ]) }}

            @if ($purchases->hasMorePages())
                ·
                <button type="button" wire:click="nextPage" class="text-brand hover:underline">
                    {{ __('marketplace.purchased.show_older') }}
                </button>
            @endif
        </p>
    @endif

</div>
