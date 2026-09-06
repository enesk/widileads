{{-- FB-034: Lead-Detail. Der Kontaktblock ist die Komponente aus FB-032 --
     die Entscheidung ueber Klartext oder verdeckt faellt dort. --}}
<div class="flex flex-col gap-4">
    @if ($lead === null)
        <div class="rounded-box bg-base-100 p-6 text-center shadow text-base-content/60">
            {{ __('leads.detail.not_found') }}
        </div>
    @else
        <x-lead.contact-card :presenter="$presenter" />

        <div class="rounded-box bg-base-100 p-6 shadow">
            <h2 class="text-base font-semibold">{{ __('leads.detail.answers') }}</h2>

            <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                @foreach ($answers as $answer)
                    <div>
                        <dt class="text-base-content/60">{{ $answer->field_key }}</dt>
                        <dd class="font-medium">
                            {{ is_array($answer->value) ? implode(', ', $answer->value) : ($answer->value ?? '-') }}
                        </dd>
                    </div>
                @endforeach
            </dl>

            <dl class="mt-6 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-base-content/60">{{ __('leads.list.score') }}</dt>
                    <dd class="font-medium">{{ $lead->score }}</dd>
                </div>
                <div>
                    <dt class="text-base-content/60">{{ __('leads.detail.result') }}</dt>
                    <dd class="font-medium">{{ $lead->result_key ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-base-content/60">{{ __('leads.detail.price_at_creation') }}</dt>
                    <dd class="font-medium">{{ $lead->price_at_creation ?? '-' }}</dd>
                </div>
            </dl>

            @if ($lead->duplicate_of_lead_id !== null)
                <p class="mt-4 text-sm text-warning">
                    {{ __('leads.detail.duplicate_of', ['id' => $lead->duplicate_of_lead_id]) }}
                </p>
            @endif
        </div>

        <div class="rounded-box bg-base-100 p-6 shadow">
            <h2 class="text-base font-semibold">{{ __('leads.detail.origin') }}</h2>

            <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-base-content/60">{{ __('leads.detail.utm_source') }}</dt>
                    <dd class="font-medium">{{ $lead->utm_source ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-base-content/60">{{ __('leads.detail.utm_campaign') }}</dt>
                    <dd class="font-medium">{{ $lead->utm_campaign ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-base-content/60">{{ __('leads.detail.embed_origin') }}</dt>
                    <dd class="font-medium">{{ $lead->embed_origin ?? '-' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-box bg-base-100 p-6 shadow">
            <h2 class="text-base font-semibold">{{ __('leads.detail.state_log') }}</h2>

            <ol class="mt-4 flex flex-col gap-2 text-sm">
                @foreach ($lead->stateLog as $entry)
                    <li class="flex flex-wrap gap-x-2">
                        <span class="text-base-content/60">{{ $entry->created_at?->format('d.m.Y H:i') }}</span>
                        <span class="font-medium">
                            {{ $entry->from_state?->label() ?? '-' }} &rarr; {{ $entry->to_state->label() }}
                        </span>
                        <span class="text-base-content/60">{{ $entry->reason->label() }}</span>
                        @if ($entry->actor !== null)
                            <span class="text-base-content/60">({{ $entry->actor->name }})</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</div>
