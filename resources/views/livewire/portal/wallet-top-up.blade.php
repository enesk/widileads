{{--
    "Guthaben aufladen" im Portal (Portal Phase 1).

    Markup nach dem Entwurf `guthaben-aufladen.html`, Klasse fuer Klasse: Kopf
    mit Rueckweg und Guthabenkarte, Formular mit Paketen, Workspace und
    Livesumme, daneben die Abrechnungsregeln und die letzten Aufladungen. Der
    Rahmen kommt aus x-layouts.portal-app -- dort steht bereits das <main> samt
    Abstaenden, hier beginnt nur der Inhalt.

    Mobile First wie im Entwurf: Die Pakete sind auf dem Telefon Zeilen und ab
    sm drei Kacheln, der Zahlknopf klebt bis md unten am Bildschirm, "Letzte
    Aufladungen" erscheint erst ab lg.

    Ein gewoehnliches Formular, kein Livewire-Aufruf: Der Checkout ist eine
    Weiterleitung auf eine andere Strecke. Die Livesumme rechnet
    resources/js/modules/topup.js im Browser ueber die data-Attribute; geprueft
    und gebucht wird ausschliesslich serverseitig.

    Erwartete Daten:
      $tenant, $balanceCents, $minEuro, $maxEuro, $leadPriceCents, $vatPercent
      $packages        euro, leads, popular
      $workspaces      uuid, name
      $recentTopUps    date, amount
      $prefillEuro     vorbelegter Betrag in Euro, 0 ohne Vorbelegung
      $marketplaceUrl, $transactionsUrl, $ordersUrl
--}}
<div>

    <a href="{{ $marketplaceUrl }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand hover:underline"><x-app.icon name="arrow-left" class="size-4" />{{ __('portal.topup.back') }}</a>

    <div class="mt-3 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
            <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.topup.heading') }}</h1>
            <p class="text-zinc-500 mt-1 max-w-prose">{{ __('portal.topup.description') }}</p>
        </div>
        <div class="card px-4 py-3 flex items-center justify-between sm:flex-col sm:items-end gap-1 sm:min-w-44">
            <span class="text-sm text-zinc-500">{{ __('portal.topup.balance_label') }}</span>
            <span class="text-xl font-semibold text-zinc-900 tabular-nums whitespace-nowrap">{{ $this->money($balanceCents) }}</span>
        </div>
    </div>

    {{-- Bei Pay as you go steht hier der offene Betrag samt Kreditrahmen;
         bei Prepaid rendert der Baustein nichts (LP-POSTPAID-010). --}}
    <div class="mt-6 empty:mt-0">
        @livewire('portal.postpaid-balance')
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem] items-start">

        <form class="card p-5 md:p-8 space-y-6 min-w-0" id="topup" method="POST" action="{{ route('buyer.wallet.topup') }}" data-balance="{{ $balanceCents / 100 }}" data-vat="{{ $vatPercent }}" data-pay-label="{{ __('portal.topup.submit', ['amount' => ':amount']) }}">
            @csrf

            <div class="flex items-start gap-4">
                <span class="size-12 rounded-xl bg-brand text-white flex items-center justify-center shrink-0"><x-app.icon name="coins" class="size-6" /></span>
                <div><h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.topup.form_heading') }}</h2><p class="text-sm text-zinc-500">{{ __('portal.topup.form_hint', ['price' => $this->money($leadPriceCents)]) }}</p></div>
            </div>

            <fieldset>
                <legend class="text-sm font-medium text-zinc-700 mb-2">{{ __('portal.topup.packages_legend') }}</legend>
                <div class="grid gap-3 sm:grid-cols-3 sm:pt-2">
                    @foreach ($packages as $package)
                        <label class="pkg flex-row sm:flex-col items-center justify-between sm:justify-center text-left sm:text-center">
                            <input type="radio" name="amount_euro" value="{{ $package['euro'] }}" class="sr-only" @checked($loop->first && $prefillEuro === 0)>
                            <span class="flex flex-col"><span class="text-lg font-semibold text-zinc-900 tabular-nums">{{ $package['euro'] }} &euro;</span><span class="text-sm text-zinc-500">{{ trans_choice('portal.topup.package_leads', $package['leads'], ['count' => $package['leads']]) }}</span></span>
                            @if ($package['popular'])
                                <span class="pill-solid text-xs sm:absolute sm:-top-3 sm:left-1/2 sm:-translate-x-1/2">{{ __('portal.topup.popular') }}</span>
                            @else
                                <span class="size-5 rounded-full border border-zinc-300 sm:hidden pkg-dot"></span>
                            @endif
                        </label>
                    @endforeach
                </div>
                <button type="button" class="mt-3 text-sm text-brand font-medium hover:underline" data-toggle="custom">{{ __('portal.topup.custom_toggle') }}</button>
                <div id="custom" @class(['mt-3', 'hidden' => $prefillEuro === 0])>
                    <label for="amount" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.topup.custom_label') }}</label>
                    <div class="relative"><input id="amount" name="amount_euro" type="number" min="{{ $minEuro }}" max="{{ $maxEuro }}" step="1" class="input pr-10" placeholder="{{ __('portal.topup.custom_placeholder', ['amount' => $minEuro * 2]) }}" inputmode="numeric" value="{{ $prefillEuro > 0 ? $prefillEuro : '' }}" @disabled($prefillEuro === 0)><span class="absolute right-4 top-1/2 -translate-y-1/2 text-zinc-400">&euro;</span></div>
                    <p class="text-xs text-zinc-500 mt-1">{{ __('portal.topup.custom_hint', ['min' => $minEuro, 'max' => $maxEuro]) }}</p>
                </div>
                @error('amount_euro')
                    <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                @enderror
            </fieldset>

            <div>
                <label for="workspace" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.topup.workspace_label') }}</label>
                <div class="relative">
                    <select id="workspace" name="tenant" class="input appearance-none pr-10">@foreach ($workspaces as $workspace)<option value="{{ $workspace['uuid'] }}" @selected($workspace['uuid'] === $tenant->uuid)>{{ $workspace['name'] }}</option>@endforeach</select>
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"><x-app.icon name="chevron-down" class="size-5 text-zinc-400" /></span>
                </div>
            </div>

            <div class="border-t border-zinc-200 pt-4 space-y-2 text-sm">
                <div class="flex items-center justify-between"><span class="text-zinc-500">{{ __('portal.topup.subtotal') }}</span><span class="text-zinc-900 tabular-nums" data-sum>{{ $this->money(($packages[0]['euro'] ?? $minEuro) * 100) }}</span></div>
                @if ($vatPercent > 0)
                    <div class="flex items-center justify-between"><span class="text-zinc-500">{{ __('portal.topup.vat', ['percent' => $vatPercent]) }}</span><span class="text-zinc-900 tabular-nums" data-vat-amount>&mdash;</span></div>
                @endif
                <div class="flex items-center justify-between pt-3 border-t border-zinc-200 text-base font-semibold text-zinc-900"><span>{{ __('portal.topup.total') }}</span><span class="tabular-nums" data-total>{{ $this->money(($packages[0]['euro'] ?? $minEuro) * 100) }}</span></div>
                <p class="text-xs text-zinc-500">{{ __('portal.topup.vat_note') }}</p>
            </div>

            <div class="rounded-xl bg-brand-50 border border-brand-100 p-4 text-sm text-brand-700 flex items-center justify-between gap-3">
                <span>{{ __('portal.topup.after') }}</span><span class="font-semibold tabular-nums whitespace-nowrap" data-after>&mdash;</span>
            </div>

            <div class="sticky bottom-0 z-10 -mx-5 md:mx-0 px-5 md:px-0 py-3 md:py-0 bg-white/95 md:bg-transparent backdrop-blur md:backdrop-blur-0 border-t md:border-0 border-zinc-200">
                <button type="submit" class="btn-primary w-full whitespace-nowrap" data-pay>{{ __('portal.topup.submit', ['amount' => $this->money(($packages[0]['euro'] ?? $minEuro) * 100)]) }}</button>
            </div>
            <div class="-mt-2">
                <p class="text-xs text-zinc-500 text-center leading-relaxed">{!! __('portal.topup.legal', ['terms' => '<a href="'.route('terms-of-service').'" class="text-brand hover:underline">'.e(__('portal.topup.legal_terms')).'</a>', 'privacy' => '<a href="'.route('privacy-policy').'" class="text-brand hover:underline">'.e(__('portal.topup.legal_privacy')).'</a>']) !!}</p>
                <p class="text-xs text-zinc-400 flex items-center justify-center gap-2 mt-2"><x-app.icon name="lock" class="size-4" />{{ __('portal.topup.payment_hint') }}</p>
            </div>
        </form>

        <aside class="space-y-4">
            <section class="card p-5">
                <h2 class="text-sm font-medium text-zinc-500">{{ __('portal.topup.rules.heading') }}</h2>
                <ul class="mt-3 space-y-2.5 text-sm text-zinc-700">
                    @foreach (__('portal.topup.rules.items', ['seconds' => config('lead_calls.answered_min_seconds'), 'attempts' => config('lead_calls.unreachable_attempts'), 'days' => config('lead_calls.unreachable_min_days')]) as $rule)
                        <li class="flex gap-2"><span class="text-brand mt-0.5"><x-app.icon name="check" class="size-4" /></span>{{ $rule }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('terms-of-service') }}" class="block text-sm text-brand hover:underline mt-4">{{ __('portal.topup.rules.link') }}</a>
            </section>
            @if ($recentTopUps !== [])
                <section class="card p-5 hidden lg:block">
                    <h2 class="text-sm font-medium text-zinc-500">{{ __('portal.topup.recent.heading') }}</h2>
                    <ul class="mt-3 divide-y divide-zinc-200 text-sm">
                        @foreach ($recentTopUps as $topUp)
                            <li class="py-2 flex justify-between"><span class="text-zinc-700">{{ $topUp['date'] }}</span><span class="text-zinc-900 tabular-nums">{{ $topUp['amount'] }}</span></li>
                        @endforeach
                    </ul>
                    <a href="{{ $transactionsUrl }}" class="block text-sm text-brand hover:underline mt-3">{{ __('portal.topup.recent.link') }}</a>
                </section>
            @endif
        </aside>

    </div>

    {{-- Der Antrag auf Pay as you go. Nur fuer einen Prepaid-Kaeufer bei
         laufendem Rollout, sonst rendert der Baustein nichts. --}}
    <div class="mt-6 empty:mt-0">
        @livewire('portal.postpaid-application')
    </div>
</div>
