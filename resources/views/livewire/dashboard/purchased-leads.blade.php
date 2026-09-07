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

    @if ($complaintError !== null)
        <div class="alert alert-warning">
            <span>{{ $complaintError }}</span>
        </div>
    @endif

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

                {{-- Reklamation (FB-058): beantragen, nicht entscheiden. --}}
                @if ($row['complaint'] !== null)
                    <div class="alert alert-info text-sm">
                        <span>{{ __('marketplace.complaint.filed', [
                            'state' => $row['complaint']->requested_state->label(),
                            'status' => $row['complaint']->status->label(),
                        ]) }}</span>
                    </div>
                @elseif ($row['canComplain'])
                    <details class="collapse collapse-arrow border border-base-300">
                        <summary class="collapse-title text-sm font-medium">
                            {{ __('marketplace.complaint.open') }}
                        </summary>
                        <div class="collapse-content space-y-2">
                            <p class="text-sm opacity-70">{{ __('marketplace.complaint.help') }}</p>

                            <select class="select select-bordered select-sm w-full"
                                    wire:model="complaintState.{{ $purchase->id }}">
                                @foreach ($complaintStates as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            <textarea class="textarea textarea-bordered w-full" rows="2"
                                      wire:model="complaintReason.{{ $purchase->id }}"
                                      placeholder="{{ __('marketplace.complaint.reason_placeholder') }}"></textarea>

                            <button type="button" class="btn btn-sm btn-warning"
                                    wire:click="fileComplaint({{ $purchase->id }})">
                                {{ __('marketplace.complaint.submit') }}
                            </button>
                        </div>
                    </details>
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
