{{--
    Kaufkriterien im Portal (Portal Phase 1).

    Markup nach dem Entwurf `kaufkriterien.html`: fuenf nummerierte Fragen
    links, die Live-Zahl rechts, die Speichern-Leiste klebt unten. Der Rahmen
    kommt aus x-layouts.portal-app.

    Diese Ansicht rechnet nicht. Trefferzahl und Zusammenfassung liegen fertig
    vor -- gezaehlt hat dieselbe Abfrage, die auch den Marktplatz fuellt.
--}}
<div>

    <div>
        <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('marketplace.profile.portal.heading') }}</h1>
        <p class="text-zinc-500 mt-1 max-w-prose">{{ __('marketplace.profile.portal.description') }}</p>
    </div>

    @if ($notice !== null)
        <div role="status" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            {{ $notice }}
        </div>
    @endif

    <form class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem] items-start" wire:submit="save">

        <div class="space-y-4 min-w-0">

            {{-- 1 Woher --}}
            <section class="card p-5 md:p-6">
                <div class="flex items-start gap-3">
                    <span class="size-8 rounded-full bg-brand text-white text-sm font-semibold flex items-center justify-center shrink-0">1</span>
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-zinc-900">{{ __('marketplace.profile.portal.source.title') }}</h2>
                        <p class="text-sm text-zinc-500">{{ __('marketplace.profile.portal.source.subtitle') }}</p>
                    </div>
                </div>
                <div class="mt-5 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 mb-1">{{ __('marketplace.profile.portal.source.label') }}</label>

                        <div class="input h-auto min-h-11 flex flex-wrap items-center gap-2 py-1.5 cursor-pointer">
                            @foreach ($funnelIds as $funnelId)
                                <span class="inline-flex items-center gap-1 pl-3 pr-1.5 py-1 rounded-full text-sm font-medium bg-brand-50 text-brand-700">
                                    {{ $funnels[$funnelId] ?? $funnelId }}
                                    <button type="button" class="size-6 rounded-full hover:bg-black/10 flex items-center justify-center"
                                            aria-label="{{ __('marketplace.profile.portal.source.remove', ['name' => $funnels[$funnelId] ?? $funnelId]) }}"
                                            wire:click="removeFunnel({{ $funnelId }})">
                                        <x-app.icon name="close" class="size-3.5 shrink-0" />
                                    </button>
                                </span>
                            @endforeach

                            {{-- Im Entwurf steht hier ein Text mit Pfeil. An
                                 derselben Stelle wird jetzt getippt: Die Liste
                                 darunter zeigt die passenden Fragebogen,
                                 Eingabe uebernimmt den ersten. --}}
                            <input type="text" role="combobox" autocomplete="off"
                                   class="flex-1 min-w-32 bg-transparent outline-none text-base placeholder:text-zinc-400"
                                   placeholder="{{ __('marketplace.profile.portal.source.placeholder') }}"
                                   aria-label="{{ __('marketplace.profile.portal.source.label') }}"
                                   aria-expanded="{{ $funnelOpen ? 'true' : 'false' }}"
                                   wire:model.live.debounce.200ms="funnelQuery"
                                   wire:focus="openFunnels"
                                   wire:keydown.enter.prevent="addFirstFunnel"
                                   wire:keydown.escape="closeFunnels">
                            <span class="ml-auto"><x-app.icon name="chevron-down" class="size-5 text-zinc-400 shrink-0" /></span>
                        </div>

                        @if ($funnelOpen && $funnelSuggestions !== [])
                            {{-- mousedown statt click: Ein Klick auf einen
                                 Vorschlag nimmt dem Feld zuerst den Fokus, und
                                 der geschlossene Kasten haette den Klick nie
                                 gesehen. --}}
                            <ul class="relative z-10 -mt-1 rounded-xl border border-zinc-200 bg-white shadow-lg overflow-hidden">
                                @foreach ($funnelSuggestions as $id => $name)
                                    <li>
                                        <button type="button" class="w-full text-left px-4 py-2.5 text-sm text-zinc-700 hover:bg-zinc-100"
                                                wire:mousedown.prevent="addFunnel({{ $id }})">
                                            {{ $name }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <p class="text-xs text-zinc-500 mt-1">{{ __('marketplace.profile.portal.source.hint') }}</p>
                    </div>
                </div>
            </section>

            {{-- 2 Wo --}}
            <section class="card p-5 md:p-6">
                <div class="flex items-start gap-3">
                    <span class="size-8 rounded-full bg-brand text-white text-sm font-semibold flex items-center justify-center shrink-0">2</span>
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-zinc-900">{{ __('marketplace.profile.portal.region.title') }}</h2>
                        <p class="text-sm text-zinc-500">{{ __('marketplace.profile.portal.region.subtitle') }}</p>
                    </div>
                </div>
                <div class="mt-5 space-y-5">
                    <div>
                        <label for="plz" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('marketplace.profile.portal.region.label') }}</label>
                        <div class="input h-auto min-h-11 flex flex-wrap items-center gap-2 py-1.5">
                            @foreach ($postalPrefixes as $prefix)
                                <span class="inline-flex items-center gap-1 pl-3 pr-1.5 py-1 rounded-full text-sm font-medium bg-zinc-100 text-zinc-800">
                                    {{ $prefix }}
                                    <button type="button" class="size-6 rounded-full hover:bg-black/10 flex items-center justify-center"
                                            aria-label="{{ __('marketplace.profile.portal.source.remove', ['name' => $prefix]) }}"
                                            wire:click="removePrefix('{{ $prefix }}')">
                                        <x-app.icon name="close" class="size-3.5 shrink-0" />
                                    </button>
                                </span>
                            @endforeach
                            <input id="plz" class="flex-1 min-w-24 bg-transparent outline-none text-base placeholder:text-zinc-400"
                                   placeholder="{{ __('marketplace.profile.portal.region.placeholder') }}" inputmode="numeric"
                                   wire:model="prefixInput" wire:keydown.enter.prevent="addPrefix" wire:blur="addPrefix">
                        </div>
                        <p class="text-xs text-zinc-500 mt-1">{{ __('marketplace.profile.portal.region.hint') }}</p>
                    </div>

                    <div class="rounded-xl bg-zinc-50 border border-zinc-200 p-4 text-sm text-zinc-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <span>{{ __('marketplace.profile.portal.region.radius') }}</span>
                        <a href="{{ $marketplaceUrl }}" class="text-brand font-medium hover:underline shrink-0">{{ __('marketplace.profile.portal.region.radius_action') }}</a>
                    </div>
                </div>
            </section>

            {{-- 3 Was --}}
            <section class="card p-5 md:p-6">
                <div class="flex items-start gap-3">
                    <span class="size-8 rounded-full bg-brand text-white text-sm font-semibold flex items-center justify-center shrink-0">3</span>
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-zinc-900">{{ __('marketplace.profile.portal.answers.title') }}</h2>
                        <p class="text-sm text-zinc-500">{{ __('marketplace.profile.portal.answers.subtitle') }}</p>
                    </div>
                </div>
                <div class="mt-5 space-y-5">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-medium text-zinc-700">{{ __('marketplace.profile.portal.answers.label') }}</p>
                            <span class="text-xs text-zinc-500">{{ __('marketplace.profile.portal.answers.active', ['count' => count($filters)]) }}</span>
                        </div>

                        <ul class="space-y-3">
                            @foreach ($filters as $index => $filter)
                                <li class="rounded-xl border border-zinc-200 p-4 space-y-3" wire:key="filter-{{ $index }}">
                                    <div class="flex items-center gap-2">
                                        <div class="relative flex-1 min-w-0">
                                            <label class="sr-only">{{ __('marketplace.profile.portal.answers.field') }}</label>
                                            <select class="input appearance-none pr-10" wire:model.live="filters.{{ $index }}.field_key">
                                                @foreach ($fields as $fieldKey => $field)
                                                    <option value="{{ $fieldKey }}">{{ $field['label'] }}</option>
                                                @endforeach
                                            </select>
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                                                <x-app.icon name="chevron-down" class="size-5 text-zinc-400 shrink-0" />
                                            </span>
                                        </div>
                                        <button type="button" class="btn-ghost px-3 text-zinc-500 hover:text-red-600 hover:bg-red-50"
                                                aria-label="{{ __('marketplace.profile.portal.answers.remove_filter') }}"
                                                wire:click="removeFilter({{ $index }})">
                                            <x-app.icon name="trash" class="size-5 shrink-0" />
                                        </button>
                                    </div>

                                    <div>
                                        <p class="text-sm text-zinc-500 mb-2">
                                            {{ __('marketplace.profile.portal.answers.accepted') }}
                                            <span class="text-zinc-400">{{ __('marketplace.profile.portal.answers.one_is_enough') }}</span>
                                        </p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($filter['values'] as $value)
                                                <span class="inline-flex items-center gap-1 pl-3 pr-1.5 py-1 rounded-full text-sm font-medium bg-brand-50 text-brand-700">
                                                    {{ $fields[$filter['field_key']]['options'][$value] ?? $value }}
                                                    <button type="button" class="size-6 rounded-full hover:bg-black/10 flex items-center justify-center"
                                                            aria-label="{{ __('marketplace.profile.portal.answers.remove_value', ['value' => $value]) }}"
                                                            wire:click="removeValue({{ $index }}, '{{ $value }}')">
                                                        <x-app.icon name="close" class="size-3.5 shrink-0" />
                                                    </button>
                                                </span>
                                            @endforeach

                                            <button type="button" class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium border border-dashed border-zinc-300 text-zinc-600 hover:border-brand hover:text-brand"
                                                    wire:click="openValuePicker({{ $index }})">
                                                <x-app.icon name="plus" class="size-4 shrink-0" />
                                                {{ __('marketplace.profile.portal.answers.add_value') }}
                                            </button>
                                        </div>

                                        {{-- Zusatz zum Entwurf: Die Antworten
                                             stehen im Fragebogen, also werden sie
                                             ausgewaehlt statt getippt. --}}
                                        @if ($valuePickerFor === $index)
                                            <div class="relative mt-2 max-w-xs">
                                                <select class="input appearance-none pr-10" wire:change="addValue({{ $index }}, $event.target.value)">
                                                    <option value="">{{ __('marketplace.profile.portal.answers.pick_value') }}</option>
                                                    @foreach ($fields[$filter['field_key']]['options'] ?? [] as $value => $label)
                                                        @unless (in_array((string) $value, $filter['values'], true))
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endunless
                                                    @endforeach
                                                </select>
                                                <span class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                                                    <x-app.icon name="chevron-down" class="size-5 text-zinc-400 shrink-0" />
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        @if ($fields === [])
                            <p class="text-sm text-zinc-500">{{ __('marketplace.profile.portal.answers.no_fields') }}</p>
                        @else
                            <button type="button" class="btn-secondary w-full sm:w-auto mt-3" wire:click="addFilter">
                                <x-app.icon name="plus" />
                                {{ __('marketplace.profile.portal.answers.add_filter') }}
                            </button>
                        @endif

                        <p class="text-xs text-zinc-500 mt-2">{{ __('marketplace.profile.portal.answers.rule') }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="score" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('marketplace.profile.portal.min_score') }}</label>
                            <input id="score" type="number" min="0" max="100" class="input tabular-nums" placeholder="{{ __('marketplace.profile.portal.min_score_placeholder') }}" inputmode="numeric" wire:model.live.debounce.500ms="minScore">
                            <p class="text-xs text-zinc-500 mt-1">{{ __('marketplace.profile.portal.min_score_hint') }}</p>
                        </div>
                        <div>
                            <label for="maxprice" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('marketplace.profile.portal.max_price') }}</label>
                            <div class="relative">
                                <input id="maxprice" type="number" min="0" step="1" class="input pr-10 tabular-nums" placeholder="{{ __('marketplace.profile.portal.max_price_placeholder') }}" inputmode="numeric" wire:model.live.debounce.500ms="maxPrice">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-zinc-400">€</span>
                            </div>
                            <p class="text-xs text-zinc-500 mt-1">{{ __('marketplace.profile.portal.max_price_hint') }}</p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- 4 Automatisch kaufen --}}
            <section class="card p-5 md:p-6">
                <div class="flex items-start gap-3">
                    <span class="size-8 rounded-full bg-brand text-white text-sm font-semibold flex items-center justify-center shrink-0">4</span>
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-zinc-900">{{ __('marketplace.profile.portal.auto.title') }}</h2>
                        <p class="text-sm text-zinc-500">{{ __('marketplace.profile.portal.auto.subtitle') }}</p>
                    </div>
                </div>
                <div class="mt-5 space-y-5">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <span class="relative inline-flex shrink-0 mt-0.5">
                            <input type="checkbox" id="auto" class="peer sr-only" wire:model.live="autoBuy">
                            <span class="h-7 w-12 rounded-full bg-zinc-300 peer-checked:bg-brand transition-colors duration-150 peer-focus-visible:ring-2 peer-focus-visible:ring-brand peer-focus-visible:ring-offset-2"></span>
                            <span class="absolute left-0.5 top-0.5 size-6 rounded-full bg-white shadow-sm transition-transform duration-150 peer-checked:translate-x-5"></span>
                        </span>
                        <span>
                            <span class="block font-medium text-zinc-900">{{ __('marketplace.profile.portal.auto.label') }}</span>
                            <span class="block text-sm text-zinc-500">{{ __('marketplace.profile.portal.auto.explainer') }}</span>
                        </span>
                    </label>

                    <div @class(['space-y-4 pl-0 sm:pl-[3.75rem]', 'hidden' => ! $autoBuy])>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="daily" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('marketplace.profile.portal.auto.daily_limit') }}</label>
                                <input id="daily" type="number" min="0" class="input tabular-nums" inputmode="numeric" wire:model="dailyLimit">
                                <p class="text-xs text-zinc-500 mt-1">{{ __('marketplace.profile.portal.auto.daily_limit_hint') }}</p>
                            </div>
                            <div>
                                <label for="budget" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('marketplace.profile.portal.auto.budget') }}</label>
                                <div class="relative">
                                    <input id="budget" type="number" min="0" step="15" class="input pr-10 tabular-nums" placeholder="{{ __('marketplace.profile.portal.auto.budget_placeholder') }}" inputmode="numeric" wire:model="weeklyBudget">
                                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-zinc-400">€</span>
                                </div>
                                <p class="text-xs text-zinc-500 mt-1">{{ __('marketplace.profile.portal.auto.budget_hint') }}</p>
                            </div>
                        </div>
                        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900 flex gap-3">
                            <span class="text-amber-500 mt-0.5"><x-app.icon name="info" class="size-5 text-amber-500 shrink-0" /></span>
                            <span>{{ __('marketplace.profile.portal.auto.warning') }}</span>
                        </div>
                    </div>
                </div>
            </section>

            {{-- 5 Benachrichtigung --}}
            <section class="card p-5 md:p-6">
                <div class="flex items-start gap-3">
                    <span class="size-8 rounded-full bg-brand text-white text-sm font-semibold flex items-center justify-center shrink-0">5</span>
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-zinc-900">{{ __('marketplace.profile.portal.notify.title') }}</h2>
                        <p class="text-sm text-zinc-500">{{ __('marketplace.profile.portal.notify.subtitle') }}</p>
                    </div>
                </div>
                <div class="mt-5 space-y-5">
                    <div>
                        <label for="notify" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('marketplace.profile.portal.notify.label') }}</label>
                        <input id="notify" type="email" class="input" autocomplete="email" wire:model="notifyEmail">
                        <p class="text-xs text-zinc-500 mt-1">{{ __('marketplace.profile.portal.notify.hint') }}</p>
                    </div>
                    <fieldset>
                        <legend class="text-sm font-medium text-zinc-700 mb-2">{{ __('marketplace.profile.portal.notify.when') }}</legend>
                        <div class="grid gap-2 sm:grid-cols-3">
                            @foreach ($intervals as $value => $label)
                                <label class="pkg flex-row sm:flex-col items-center justify-between sm:justify-center py-3 text-left sm:text-center">
                                    <input type="radio" name="when" value="{{ $value }}" class="sr-only" wire:model.live="notifyInterval">
                                    <span class="text-sm font-medium text-zinc-900">{{ $label }}</span>
                                    <span class="size-5 rounded-full border border-zinc-300 sm:hidden pkg-dot"></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
            </section>

            {{-- Speichern-Leiste. Klebt auf schmalen Bildschirmen unten, damit
                 der Stand nicht am Ende einer langen Seite verschwindet. --}}
            <div class="sticky bottom-0 z-10 -mx-4 md:mx-0 px-4 md:px-0 py-3 md:py-0 bg-zinc-50/95 backdrop-blur md:bg-transparent md:backdrop-blur-0 border-t md:border-0 border-zinc-200 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2">
                <span class="text-sm text-zinc-500 text-center sm:text-left">
                    {{ $isDirty ? __('marketplace.profile.portal.dirty') : __('marketplace.profile.portal.clean') }}
                </span>
                <div class="flex gap-2">
                    <button type="button" class="btn-ghost flex-1 sm:flex-none" wire:click="discard">{{ __('marketplace.profile.portal.discard') }}</button>
                    <button type="submit" class="btn-primary flex-1 sm:flex-none">{{ __('marketplace.profile.portal.save') }}</button>
                </div>
            </div>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-24">
            {{-- Die wichtigste Zahl der Seite: ob die Kriterien den Marktplatz
                 gerade leer filtern. Gezaehlt hat dieselbe Abfrage wie dort. --}}
            <section class="card p-5 border-brand-100">
                <h2 class="text-sm font-medium text-zinc-500">{{ __('marketplace.profile.portal.match.heading') }}</h2>
                <p class="mt-1 text-3xl font-semibold text-zinc-900 tabular-nums">
                    {{ trans_choice('marketplace.profile.portal.match.count', $matchCount, ['count' => $matchCount]) }}
                </p>
                <p class="text-sm text-zinc-500">
                    {!! __('marketplace.profile.portal.match.context', ['count' => '<span class="tabular-nums">'.$recentCount.'</span>']) !!}
                </p>

                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($summary as $line)
                        <li @class(['flex items-start gap-2', 'text-zinc-500' => ! $line['active']])>
                            @if ($line['active'])
                                <span class="text-brand mt-0.5"><x-app.icon name="check" class="size-4 shrink-0" /></span>
                            @else
                                <span class="mt-0.5 size-4 rounded-full border border-zinc-300 shrink-0"></span>
                            @endif
                            <span>
                                {{ $line['label'] }}
                                @if ($line['value'] !== '')
                                    <span class="font-medium text-zinc-900">{{ $line['value'] }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>

                <a href="{{ $marketplaceUrl }}" class="btn-secondary w-full mt-4">{{ __('marketplace.profile.portal.match.view') }}</a>
            </section>

            <section class="card p-5 hidden lg:block">
                <h2 class="text-sm font-medium text-zinc-500">{{ __('marketplace.profile.portal.tip_heading') }}</h2>
                <p class="mt-2 text-sm text-zinc-700">{{ __('marketplace.profile.portal.tip') }}</p>
            </section>
        </aside>

    </form>
</div>
