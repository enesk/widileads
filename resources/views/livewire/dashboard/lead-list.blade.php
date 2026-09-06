{{-- FB-034: Lead-Liste des Betreibers. Kontaktdaten kommen fertig entschieden
     aus dem LeadPresenter -- hier steht keine Bedingung darueber, was jemand
     sehen darf. --}}
<div class="flex flex-col gap-6">
    <section class="rounded-box bg-base-100 p-4 shadow" aria-label="{{ __('leads.list.filters') }}">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('leads.list.search') }}</span>
                <input type="search" wire:model.live.debounce.400ms="search" class="input input-bordered"
                       placeholder="{{ $searchesContacts ? __('leads.list.search_placeholder_contacts') : __('leads.list.search_placeholder_name') }}">
                @unless ($searchesContacts)
                    <span class="text-xs text-base-content/50">{{ __('leads.list.search_name_only') }}</span>
                @endunless
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('leads.list.funnel') }}</span>
                <select wire:model.live="funnelId" class="select select-bordered">
                    <option value="">{{ __('leads.list.all') }}</option>
                    @foreach ($funnels as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('leads.list.state') }}</span>
                <select wire:model.live="leadState" class="select select-bordered">
                    <option value="">{{ __('leads.list.all') }}</option>
                    @foreach ($states as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('leads.list.postal_prefix') }}</span>
                <input type="text" inputmode="numeric" maxlength="5" wire:model.live.debounce.400ms="postalPrefix"
                       class="input input-bordered" placeholder="76">
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('leads.list.from') }}</span>
                <input type="date" wire:model.live="from" class="input input-bordered">
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('leads.list.until') }}</span>
                <input type="date" wire:model.live="until" class="input input-bordered">
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('leads.list.score_min') }}</span>
                <input type="number" min="0" wire:model.live.debounce.400ms="scoreMin" class="input input-bordered">
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-base-content/60">{{ __('leads.list.score_max') }}</span>
                <input type="number" min="0" wire:model.live.debounce.400ms="scoreMax" class="input input-bordered">
            </label>
        </div>

        <button type="button" wire:click="resetFilters" class="btn btn-ghost btn-sm mt-3">
            {{ __('leads.list.reset') }}
        </button>
    </section>

    <section class="rounded-box bg-base-100 shadow">
        <div class="overflow-x-auto">
            <table class="table">
                <caption class="sr-only">{{ __('leads.list.heading') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('leads.list.received_at') }}</th>
                        <th scope="col">{{ __('leads.contact.name') }}</th>
                        <th scope="col">{{ __('leads.list.funnel') }}</th>
                        <th scope="col">{{ __('leads.list.state') }}</th>
                        <th scope="col">{{ __('leads.list.score') }}</th>
                        <th scope="col">{{ __('leads.contact.postal_code') }}</th>
                        <th scope="col"><span class="sr-only">{{ __('leads.list.open') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="lead-{{ $row['lead']->id }}" @class(['bg-warning/10' => $row['stale']])>
                            <td class="whitespace-nowrap">{{ $row['lead']->created_at?->format('d.m.Y H:i') }}</td>
                            <td>
                                {{ $row['presenter']->name() }}
                                @if ($row['stale'])
                                    <span class="badge badge-warning badge-sm ml-2"
                                          title="{{ __('leads.list.stale_hint', ['days' => $staleAfterDays]) }}">
                                        {{ __('leads.list.stale') }}
                                    </span>
                                @endif
                            </td>
                            <td>{{ $row['lead']->funnel?->name ?? '-' }}</td>
                            <td>{{ $row['lead']->lead_state->label() }}</td>
                            <td>{{ $row['lead']->score }}</td>
                            <td>{{ $row['presenter']->postalCode() }}</td>
                            <td class="text-right">
                                <button type="button" wire:click="select({{ $row['lead']->id }})" class="btn btn-sm">
                                    {{ __('leads.list.open') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-base-content/60">
                                {{ __('leads.list.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">{{ $page->links() }}</div>
    </section>

    @if ($selectedLeadId !== null)
        <livewire:dashboard.lead-detail :lead-id="$selectedLeadId" :key="'lead-detail-'.$selectedLeadId" />

        <button type="button" wire:click="closeDetail" class="btn btn-ghost btn-sm self-start">
            {{ __('leads.detail.close') }}
        </button>
    @endif
</div>
