{{-- FB-032: Kontaktblock eines Leads.

     Alle Werte kommen fertig entschieden aus dem LeadPresenter -- hier steht
     bewusst keine Bedingung darueber, was jemand sehen darf. Wer die Ausgabe
     aendert, kann die Maskierung nicht versehentlich aufheben. --}}
@props(['presenter'])

<div class="rounded-box bg-base-100 p-6 shadow" data-lead-contact>
    <h2 class="text-base font-semibold">{{ __('leads.contact.heading') }}</h2>

    @if ($presenter->isContactMasked())
        <p class="mt-1 text-sm text-base-content/70">{{ __('leads.contact.masked_hint') }}</p>
    @endif

    <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
        <div>
            <dt class="text-base-content/60">{{ __('leads.contact.name') }}</dt>
            <dd class="font-medium">{{ $presenter->name() }}</dd>
        </div>
        <div>
            <dt class="text-base-content/60">{{ __('leads.contact.email') }}</dt>
            <dd class="font-medium">{{ $presenter->email() }}</dd>
        </div>
        <div>
            <dt class="text-base-content/60">{{ __('leads.contact.phone') }}</dt>
            <dd class="font-medium">{{ $presenter->phone() }}</dd>
            @if ($presenter->phoneHint() !== null)
                <dd class="mt-1 text-xs text-base-content/60">{{ $presenter->phoneHint() }}</dd>
            @endif
        </div>
        <div>
            <dt class="text-base-content/60">{{ __('leads.contact.postal_code') }}</dt>
            <dd class="font-medium">{{ $presenter->postalCode() }}</dd>
        </div>
    </dl>
</div>
