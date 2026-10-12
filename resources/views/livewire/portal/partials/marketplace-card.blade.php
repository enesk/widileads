{{--
    Eine Lead-Karte im Marktplatz.

    Das Aussehen steht in x-app.lead-card (LP-ONBOARD-020) -- hier steht nur
    noch, was diese Karte mit der Livewire-Komponente dahinter verbindet: Die
    ganze Karte oeffnet das Kaufblatt, der Kaufknopf tut dasselbe. Gekauft wird
    ausschliesslich im Blatt -- dort steht, was der Kaeufer bekommt, bevor Geld
    reserviert wird.

    Steht in der Liste und in der Gruppenansicht, deshalb als eigene Datei.

    $tourStep sagt, ob diese Karte die Halte 2 und 4 der gefuehrten Tour tragen
    soll (LP-ONBOARD-013). Genau eine Karte je Liste tut das -- die erste.

    Die Marken stehen als :data-tour="..."-Ausdruecke und nicht in einem @if:
    Ein @if innerhalb eines Komponenten-Tags versteht Blade nicht, und ein
    Attribut mit dem Wert null laesst der Attributbeutel von selbst weg.
--}}
@php($tourStep = $tourStep ?? false)

<x-app.lead-card
    :lead="$lead"
    :price-hint="$surchargeHint"
    :title-attributes="['wire:click' => 'openLead('.$lead['id'].')']"
    wire:key="lead-{{ $lead['id'] }}"
    :data-tour="$tourStep ? 'marketplace:2' : null"
    :data-tour-title="$tourStep ? __('tour.marketplace.lead.title') : null"
    :data-tour-text="$tourStep ? __('tour.marketplace.lead.text') : null"
    :data-tour-side="$tourStep ? 'bottom' : null"
    :data-tour-align="$tourStep ? 'start' : null"
>
    <x-slot:action>
        <button
            type="button"
            class="btn-primary whitespace-nowrap disabled:opacity-50"
            wire:click="openLead({{ $lead['id'] }})"
            @disabled(! $lead['purchasable'])
            @if ($tourStep)
                data-tour="marketplace:4"
                data-tour-title="{{ __('tour.marketplace.purchase.title') }}"
                data-tour-text="{{ __('tour.marketplace.purchase.text') }}"
                data-tour-side="bottom"
                data-tour-align="end"
            @endif
        >
            <x-app.icon name="cart" class="size-5" />
            {{ __('marketplace.listing.purchase_short') }}
        </button>
    </x-slot:action>
</x-app.lead-card>
