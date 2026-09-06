{{--
    FB-053: Marktplatzliste. Tailwind und daisyUI, keine Filament-Komponenten.

    Kontaktdaten stehen hier ausschliesslich als $row['presenter']->…() -- der
    Presenter liefert sie bereits fertig maskiert (FB-032). In diesem Template
    wird nie entschieden, was ein Kaeufer sehen darf.
--}}
<div class="space-y-4">
    <p class="text-sm opacity-70">{{ __('marketplace.listing.description') }}</p>

    @unless ($hasProfile)
        <div class="alert">
            <span>{{ __('marketplace.listing.no_profile') }}</span>
        </div>
    @endunless

    <div class="flex flex-wrap items-end gap-4">
        <div class="form-control">
            <label class="label" for="sort">
                <span class="label-text">{{ __('marketplace.listing.sort') }}</span>
            </label>
            <select id="sort" wire:model.live="sort" class="select select-bordered">
                <option value="newest">{{ __('marketplace.listing.sort_newest') }}</option>
                <option value="score">{{ __('marketplace.listing.sort_score') }}</option>
            </select>
        </div>

        <label class="label cursor-pointer gap-3">
            <input type="checkbox" wire:model.live="onlyWatchlisted" class="checkbox">
            <span class="label-text">{{ __('marketplace.listing.only_watchlisted') }}</span>
        </label>
    </div>

    @forelse ($rows as $row)
        @php($lead = $row['lead'])
        @php($presenter = $row['presenter'])

        <div class="card bg-base-100 shadow">
            <div class="card-body gap-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="card-title text-base">{{ $presenter->name() }}</h2>
                        <p class="text-sm opacity-70">
                            {{ $lead->funnel?->name ?? __('marketplace.listing.unknown_funnel') }}
                            &middot; {{ $lead->created_at?->diffForHumans() }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge badge-outline">
                            {{ __('marketplace.listing.score', ['score' => $lead->score]) }}
                        </span>

                        @if ($row['isTaken'])
                            <span class="badge badge-warning">{{ __('marketplace.listing.taken') }}</span>
                        @endif
                    </div>
                </div>

                <div class="grid gap-2 text-sm md:grid-cols-3">
                    <div>
                        <span class="opacity-70">{{ __('marketplace.listing.region') }}:</span>
                        {{ $presenter->postalCode() }}
                    </div>
                    <div>
                        <span class="opacity-70">{{ __('marketplace.listing.email') }}:</span>
                        {{ $presenter->email() }}
                    </div>
                    <div>
                        <span class="opacity-70">{{ __('marketplace.listing.phone') }}:</span>
                        {{ $presenter->phone() }}
                    </div>
                </div>

                @if ($lead->result_key !== null)
                    <div class="text-sm">
                        <span class="opacity-70">{{ __('marketplace.listing.result') }}:</span>
                        {{ $lead->result_key }}
                    </div>
                @endif

                @if ($row['qualification'] !== [])
                    <div class="flex flex-wrap gap-2">
                        @foreach ($row['qualification'] as $fieldKey => $value)
                            <span class="badge badge-ghost">{{ $fieldKey }}: {{ $value }}</span>
                        @endforeach
                    </div>
                @endif

                <div class="card-actions items-center justify-end gap-2">
                    @if ($presenter->isContactMasked())
                        <span class="text-xs opacity-70">{{ __('marketplace.listing.masked_hint') }}</span>
                    @endif

                    <button type="button" class="btn btn-ghost btn-sm"
                            wire:click="toggleWatchlist({{ $lead->id }})">
                        {{ $row['isWatchlisted']
                            ? __('marketplace.listing.unwatch')
                            : __('marketplace.listing.watch') }}
                    </button>

                    {{-- Der Kauf selbst kommt mit FB-054; bis dahin steht der
                         Knopf, ohne zu wirken. --}}
                    <button type="button" class="btn btn-primary btn-sm"
                            @disabled(! $purchaseAvailable || ! $row['canPurchase'])
                            title="{{ $purchaseAvailable ? '' : __('marketplace.listing.purchase_unavailable') }}">
                        {{ __('marketplace.listing.purchase') }}
                    </button>
                </div>
            </div>
        </div>
    @empty
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <p>{{ __('marketplace.listing.empty') }}</p>
            </div>
        </div>
    @endforelse

    <div>
        {{ $rows->links() }}
    </div>
</div>
