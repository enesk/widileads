{{--
    Dashboard des Kaeufers (Portal Phase 1).

    Markup nach dem Entwurf `dashboard.html`: Kopfzeile, vier Kennzahlen,
    darunter der Handlungsbedarf, ganz unten die Statistik. Der Rahmen kommt aus
    x-layouts.portal-app.

    Diese Ansicht rechnet nicht und entscheidet nicht. Alle Werte liegen fertig
    vor -- Kontaktdaten so, wie der LeadPresenter sie geliefert hat, Preise so,
    wie die Kauf-Zusage sie nennt.
--}}
<div>

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <p class="text-sm text-zinc-500">{{ $today }}</p>
            <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ $greeting }}</h1>
            <p class="text-zinc-500 mt-1">{{ $summary }}</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2 shrink-0">
            <a href="{{ $urls['wallet'] }}" class="btn-secondary">
                <x-app.icon name="wallet" />
                {{ __('portal.dashboard.top_up') }}
            </a>
            <a href="{{ $urls['marketplace'] }}" class="btn-primary">
                <x-app.icon name="cart" />
                {{ __('portal.dashboard.to_marketplace') }}
            </a>
        </div>
    </div>

    @if ($notice !== null)
        <div role="status" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            {{ $notice }}
        </div>
    @endif

    {{-- Bei Pay as you go tritt der offene Betrag samt Kreditrahmen an die
         Stelle der Guthabenkarte: "Verfuegbar" allein verschwiege, dass am
         naechsten Einzugstermin abgebucht wird (LP-POSTPAID-010). --}}
    @if ($postpaid)
        <div class="mt-6">
            @livewire('portal.postpaid-balance')
        </div>
    @endif

    {{-- Vier Kennzahlen. Die offenen Anrufe tragen den Amber-Rand, sobald heute
         eine Frist endet -- das ist die Karte, die Geld kostet. --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @unless ($postpaid)
            <div class="card p-5 flex items-start gap-4 min-w-0">
                <span class="size-10 rounded-xl bg-brand-50 text-brand flex items-center justify-center shrink-0"><x-app.icon name="wallet" /></span>
                <div class="min-w-0">
                    <p class="text-sm text-zinc-500">{{ __('portal.dashboard.kpi.balance') }}</p>
                    <p class="text-2xl font-semibold text-zinc-900 tabular-nums whitespace-nowrap">{{ $balance }}</p>
                    <p class="text-sm text-zinc-500 truncate">{{ __('portal.dashboard.kpi.reserved', ['amount' => $reserved]) }}</p>
                </div>
            </div>
        @endunless

        <div class="card p-5 flex items-start gap-4 min-w-0">
            <span class="size-10 rounded-xl bg-brand-50 text-brand flex items-center justify-center shrink-0"><x-app.icon name="leads" /></span>
            <div class="min-w-0">
                <p class="text-sm text-zinc-500">{{ __('portal.dashboard.kpi.new_leads') }}</p>
                <p class="text-2xl font-semibold text-zinc-900 tabular-nums whitespace-nowrap">{{ $freshCount }}</p>
                <p class="text-sm text-zinc-500 truncate">{{ __('portal.dashboard.kpi.new_leads_meta', ['count' => $matchCount]) }}</p>
            </div>
        </div>

        <div @class(['card p-5 flex items-start gap-4 min-w-0', 'border-amber-200' => $dueToday > 0])>
            <span class="size-10 rounded-xl bg-brand-50 text-brand flex items-center justify-center shrink-0"><x-app.icon name="phone" /></span>
            <div class="min-w-0">
                <p class="text-sm text-zinc-500">{{ __('portal.dashboard.kpi.open_calls') }}</p>
                <p class="text-2xl font-semibold text-zinc-900 tabular-nums whitespace-nowrap">{{ $openCalls }}</p>
                <p class="text-sm text-zinc-500 truncate">{{ trans_choice('portal.dashboard.kpi.due_today', $dueToday, ['count' => $dueToday]) }}</p>
            </div>
        </div>

        <div class="card p-5 flex items-start gap-4 min-w-0">
            <span class="size-10 rounded-xl bg-brand-50 text-brand flex items-center justify-center shrink-0"><x-app.icon name="trend-up" class="size-4 shrink-0" /></span>
            <div class="min-w-0">
                <p class="text-sm text-zinc-500">{{ __('portal.dashboard.kpi.reached') }}</p>
                <p class="text-2xl font-semibold text-zinc-900 tabular-nums whitespace-nowrap">
                    {{ __('portal.dashboard.kpi.reached_value', ['captured' => $stats['captured'], 'decided' => $stats['captured'] + $stats['released']]) }}
                </p>
                <p class="text-sm text-zinc-500 truncate">
                    {{ __('portal.dashboard.kpi.reached_meta', ['rate' => $stats['rate'], 'released' => $stats['released']]) }}
                </p>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem] items-start">

        <div class="space-y-6 min-w-0">

            {{-- Der Handlungsbedarf steht oben: Hier entscheidet sich, ob ein
                 Lead berechnet oder freigegeben wird. --}}
            <section class="card p-5 md:p-6 border-amber-200">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.dashboard.deadlines.heading') }}</h2>
                    <a href="{{ $urls['leads'] }}" class="text-sm font-medium text-brand hover:underline shrink-0">{{ __('portal.dashboard.deadlines.all') }}</a>
                </div>

                @if ($deadlines === [])
                    <p class="mt-3 text-sm text-zinc-500">{{ __('portal.dashboard.deadlines.empty') }}</p>
                @else
                    <ul class="mt-2 divide-y divide-zinc-200">
                        @foreach ($deadlines as $row)
                            <li class="py-3 flex flex-col sm:flex-row sm:items-center gap-3" wire:key="deadline-{{ $row['id'] }}">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ $row['url'] }}" class="font-semibold text-zinc-900 hover:underline truncate">{{ $row['name'] }}</a>
                                        <span @class(['shrink-0', 'pill bg-amber-50 text-amber-800' => $row['urgent'], 'pill-solid' => ! $row['urgent']])>
                                            <x-app.icon name="clock" class="size-4 shrink-0" />
                                            {{ $row['deadline'] }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-zinc-500 flex items-center gap-1.5 mt-0.5">
                                        <x-app.icon name="pin" class="size-4 text-zinc-400 shrink-0" />
                                        {{ $row['meta'] }}
                                    </p>
                                </div>
                                <button type="button" class="btn-primary sm:w-auto" wire:click="startCall({{ $row['id'] }})">
                                    <x-app.icon name="phone" />
                                    {{ __('portal.dashboard.deadlines.call') }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <p class="text-xs text-zinc-500 pt-3 border-t border-zinc-200">{{ __('portal.dashboard.deadlines.rule') }}</p>
            </section>

            <section>
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.dashboard.suggestions.heading') }}</h2>
                    <a href="{{ $urls['marketplace'] }}" class="text-sm font-medium text-brand hover:underline shrink-0">{{ __('portal.dashboard.suggestions.all') }}</a>
                </div>

                @if ($suggestions === [])
                    <p class="text-sm text-zinc-500">{{ __('portal.dashboard.suggestions.empty') }}</p>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                        @foreach ($suggestions as $lead)
                            <article class="card-interactive p-5 flex flex-col gap-3 min-w-0">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="font-semibold text-zinc-900 truncate">{{ $lead['name'] }}</h3>
                                        <p class="text-sm text-zinc-500">{{ $lead['age'] }}</p>
                                    </div>
                                    <span class="pill-solid shrink-0">{{ __('portal.dashboard.suggestions.badge') }}</span>
                                </div>

                                <p class="text-sm text-zinc-700 flex items-center gap-1.5">
                                    <x-app.icon name="pin" class="size-4 text-zinc-400 shrink-0" />
                                    {{ $lead['region'] }}
                                </p>

                                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                                    @foreach ($lead['attributes'] as $label => $value)
                                        <dt class="text-zinc-500">{{ $label }}</dt>
                                        <dd class="text-zinc-900 font-medium">{{ $value }}</dd>
                                    @endforeach
                                </dl>

                                <div class="mt-auto flex items-center justify-between gap-3 pt-2 border-t border-zinc-200">
                                    <span class="text-sm text-zinc-500 flex items-center gap-1.5">
                                        <x-app.icon name="lock" class="size-4 shrink-0" />
                                        {{ $lead['price'] }}
                                    </span>
                                    <a href="{{ $lead['url'] }}" class="btn-secondary px-4 min-h-10 text-sm">{{ __('portal.dashboard.suggestions.view') }}</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="card p-5 md:p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.dashboard.stats.heading') }}</h2>
                    <span class="text-sm text-zinc-500">{{ __('portal.dashboard.stats.range', ['from' => $stats['from'], 'to' => $stats['to']]) }}</span>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <p class="text-sm text-zinc-500">{{ __('portal.dashboard.stats.bought') }}</p>
                        <p class="text-2xl font-semibold text-zinc-900 tabular-nums">{{ trans_choice('portal.dashboard.stats.bought_value', $stats['bought'], ['count' => $stats['bought']]) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-zinc-500">{{ __('portal.dashboard.stats.captured') }}</p>
                        <p class="text-2xl font-semibold text-zinc-900 tabular-nums">{{ \App\Support\Money::format($stats['captured_cents']) }}</p>
                        <p class="text-xs text-zinc-500">{{ trans_choice('portal.dashboard.stats.captured_meta', $stats['captured'], ['count' => $stats['captured']]) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-zinc-500">{{ __('portal.dashboard.stats.released') }}</p>
                        <p class="text-2xl font-semibold text-emerald-700 tabular-nums">{{ \App\Support\Money::format($stats['released_cents']) }}</p>
                        <p class="text-xs text-zinc-500">{{ trans_choice('portal.dashboard.stats.released_meta', $stats['released'], ['count' => $stats['released']]) }}</p>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span class="text-zinc-500">{{ __('portal.dashboard.stats.rate') }}</span>
                        <span class="font-medium text-zinc-900 tabular-nums">{{ $stats['rate'] }} %</span>
                    </div>
                    <div class="h-2 rounded-full bg-zinc-100 overflow-hidden">
                        <div class="h-full bg-brand rounded-full" style="width:{{ $stats['rate'] }}%"></div>
                    </div>
                    <p class="text-xs text-zinc-500 mt-1">{{ __('portal.dashboard.stats.average', ['rate' => $stats['average']]) }}</p>
                </div>
            </section>

        </div>

        <aside class="space-y-4">

            <section class="card p-5">
                <h2 class="text-sm font-medium text-zinc-500">{{ __('portal.dashboard.criteria.heading') }}</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($criteria as $line)
                        <li @class(['flex items-start gap-2', 'text-zinc-500' => ! $line['active']])>
                            @if ($line['active'])
                                <span class="text-brand mt-0.5"><x-app.icon name="check" class="size-4 shrink-0" /></span>
                            @else
                                <span class="mt-0.5 size-4 rounded-full border border-zinc-300 shrink-0"></span>
                            @endif
                            <span>{{ $line['text'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ $urls['criteria'] }}" class="btn-secondary w-full mt-4">
                    <x-app.icon name="sliders" />
                    {{ __('portal.dashboard.criteria.action') }}
                </a>
            </section>

            <section class="card p-5">
                <h2 class="text-sm font-medium text-zinc-500">{{ __('portal.dashboard.activity.heading') }}</h2>

                @if ($activity === [])
                    <p class="mt-2 text-sm text-zinc-500">{{ __('portal.dashboard.activity.empty') }}</p>
                @else
                    <ul class="mt-2 divide-y divide-zinc-100">
                        @foreach ($activity as $entry)
                            <li class="flex items-start gap-3 py-2.5">
                                <span @class([
                                    'size-8 rounded-full flex items-center justify-center shrink-0',
                                    'bg-brand-50 text-brand' => $entry['tone'] === 'brand',
                                    'bg-emerald-50 text-emerald-700' => $entry['tone'] === 'emerald',
                                    'bg-zinc-100 text-zinc-500' => $entry['tone'] === 'zinc',
                                ])>
                                    <x-app.icon :name="$entry['icon']" class="size-4 shrink-0" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm text-zinc-900">{{ $entry['text'] }}</p>
                                    <p class="text-xs text-zinc-500">{{ $entry['when'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <a href="{{ $urls['transactions'] }}" class="block text-sm text-brand hover:underline mt-3">{{ __('portal.dashboard.activity.all') }}</a>
            </section>

            <section class="card p-5">
                <h2 class="text-sm font-medium text-zinc-500">{{ __('portal.dashboard.caller_id.heading') }}</h2>

                @if ($callerId === null)
                    <p class="mt-2 text-sm text-zinc-700">{{ __('portal.dashboard.caller_id.missing') }}</p>
                    <a href="{{ $urls['callerId'] }}" class="inline-flex items-center gap-1 text-sm text-brand hover:underline mt-3">
                        {{ __('portal.dashboard.caller_id.verify') }}
                        <x-app.icon name="arrow-right" class="size-4 shrink-0" />
                    </a>
                @else
                    <p class="mt-2 text-sm text-zinc-900 font-medium">{{ $callerId['label'] }} · <span class="tabular-nums">{{ $callerId['number'] }}</span></p>
                    <p class="text-xs text-zinc-500">{{ __('portal.dashboard.caller_id.default', ['app' => config('app.wordmark')]) }}</p>
                    <a href="{{ $urls['callerId'] }}" class="inline-flex items-center gap-1 text-sm text-brand hover:underline mt-3">
                        {{ __('portal.dashboard.caller_id.action') }}
                        <x-app.icon name="arrow-right" class="size-4 shrink-0" />
                    </a>
                @endif
            </section>

        </aside>
    </div>
</div>
