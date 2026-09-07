{{--
    FB-057: Gekaufte Leads. Kontaktdaten stehen hier im Klartext, weil der
    Kaufbeleg existiert -- entschieden wird das im LeadContactResolver, nicht
    in diesem Template. Ausgegeben wird nur, was der Presenter herausgibt.
--}}
<div class="space-y-4">
    <p class="text-sm opacity-70">{{ __('marketplace.purchased.description') }}</p>

    <div class="flex flex-wrap items-center gap-4">
        <label class="label cursor-pointer gap-3">
            <input type="checkbox" wire:model.live="onlyWithoutFeedback" class="checkbox">
            <span class="label-text">{{ __('marketplace.purchased.only_without_feedback') }}</span>
        </label>

        <button type="button" class="btn btn-outline btn-sm" wire:click="exportCsv">
            {{ __('marketplace.purchased.export') }}
        </button>
    </div>

    @forelse ($rows as $row)
        @php($purchase = $row['purchase'])
        @php($presenter = $row['presenter'])

        <div class="card bg-base-100 shadow">
            <div class="card-body gap-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="card-title text-base">{{ $presenter->name() }}</h2>
                        <p class="text-sm opacity-70">
                            {{ $purchase->lead->funnel?->name ?? __('marketplace.listing.unknown_funnel') }}
                            &middot; {{ __('marketplace.purchased.bought_at', [
                                'date' => $purchase->purchased_at?->isoFormat('LLL'),
                            ]) }}
                        </p>
                    </div>

                    <span class="badge badge-outline">
                        {{ __('marketplace.listing.score', ['score' => $purchase->lead->score]) }}
                    </span>
                </div>

                <div class="grid gap-2 text-sm md:grid-cols-3">
                    <div>
                        <span class="opacity-70">{{ __('marketplace.listing.email') }}:</span>
                        {{ $presenter->email() }}
                    </div>
                    <div>
                        <span class="opacity-70">{{ __('marketplace.listing.phone') }}:</span>
                        {{ $presenter->phone() }}
                    </div>
                    <div>
                        <span class="opacity-70">{{ __('marketplace.listing.region') }}:</span>
                        {{ $presenter->postalCode() }}
                    </div>
                </div>

                @if ($row['qualification'] !== [])
                    <div class="flex flex-wrap gap-2">
                        @foreach ($row['qualification'] as $fieldKey => $value)
                            <span class="badge badge-ghost">{{ $fieldKey }}: {{ $value }}</span>
                        @endforeach
                    </div>
                @endif

                <div class="card-actions items-center justify-end gap-2">
                    @if ($purchase->buyer_feedback !== null)
                        <span class="text-xs opacity-70">
                            {{ __('marketplace.purchased.feedback_given', [
                                'feedback' => $purchase->buyer_feedback->label(),
                            ]) }}
                        </span>
                    @endif

                    @foreach ($feedbackOptions as $value => $label)
                        <button type="button"
                                class="btn btn-sm {{ $purchase->buyer_feedback?->value === $value ? 'btn-primary' : 'btn-ghost' }}"
                                wire:click="setFeedback({{ $purchase->id }}, '{{ $value }}')">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @empty
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <p>{{ __('marketplace.purchased.empty') }}</p>
            </div>
        </div>
    @endforelse

    <div>
        {{ $rows->links() }}
    </div>
</div>
