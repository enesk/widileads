{{--
    Eine Lead-Karte im Marktplatz (Entwurf `widileads-funnel-layout.html`).

    Steht in der Liste und in der Gruppenansicht, deshalb als eigene Datei.
    Links das Symbol des Funnels, in der Mitte Name, Herkunft und Merkmale,
    rechts Zeit, Preis und Kaufknopf. Unter 900 Pixeln rutscht die rechte Spalte
    als Zeile unter die Karte.

    Die ganze Karte oeffnet das Kaufblatt; der Kaufknopf tut dasselbe. Kauft
    wird ausschliesslich im Blatt -- dort steht, was der Kaeufer bekommt, bevor
    Geld reserviert wird.
--}}
<article
    wire:key="lead-{{ $lead['id'] }}"
    class="relative grid grid-cols-[44px_1fr_auto] items-start gap-x-[16px] gap-y-[8px] rounded-[16px] border border-zinc-200 bg-white p-[20px] transition-colors hover:border-zinc-400 max-[900px]:grid-cols-[44px_1fr]"
>
    <div class="grid size-[44px] place-items-center rounded-[12px] bg-brand-50 text-brand">
        <x-app.icon :name="$lead['funnel_icon']" class="size-[22.5px]" />
    </div>

    <div class="min-w-0">
        {{-- Der Name traegt die Flaeche der ganzen Karte (after:inset-0). --}}
        <button
            type="button"
            class="block max-w-full truncate text-left text-[19.8px] font-semibold leading-[1.3] text-zinc-900 after:absolute after:inset-0 after:rounded-[16px]"
            wire:click="openLead({{ $lead['id'] }})"
        >{{ $lead['name'] }}</button>

        <div class="mt-[2px] text-[16.2px] text-zinc-500">
            <span class="font-medium text-zinc-700">{{ $lead['funnel_name'] }}</span>
            @if ($lead['portal'] !== null)
                · {{ $lead['portal'] }}
            @endif
            @if ($lead['company'] !== null)
                · {{ __('marketplace.listing.for_company') }} <span class="text-brand">{{ $lead['company'] }}</span>
            @endif
            · {{ __('marketplace.listing.postal_code_short') }} {{ $lead['postal_code'] }}
        </div>

        @if ($lead['chips'] !== [])
            <div class="mt-[10px] flex flex-wrap gap-[6px]">
                @foreach ($lead['chips'] as $chip)
                    <span class="max-w-full truncate rounded-[6px] bg-zinc-100 px-[8px] py-[3px] text-[15.3px] text-zinc-700" title="{{ $chip }}">{{ $chip }}</span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="flex flex-col items-end justify-between gap-[12px] self-stretch max-[900px]:col-span-full max-[900px]:flex-row max-[900px]:items-center">
        <div class="flex items-center gap-[8px] whitespace-nowrap text-[15.3px] text-zinc-500">
            @if ($lead['is_new'])
                <span class="rounded-full bg-brand px-[10px] py-[2px] text-[14.4px] font-semibold text-white">{{ __('marketplace.listing.badge_new') }}</span>
            @endif
            <span title="{{ $lead['created_at_exact'] }}">{{ $lead['created_at'] }}</span>
        </div>

        {{-- Preis und Kaufknopf liegen ueber der Kartenflaeche (z-10). --}}
        <div class="relative z-10 flex items-center gap-[16px]">
            <span class="text-[18.9px] font-semibold text-zinc-900 tabular-nums"
                @if ($surchargeHint !== null) title="{{ $surchargeHint }}" @endif
            >{{ $lead['price'] }}</span>
            <button
                type="button"
                class="inline-flex min-h-[44px] items-center gap-[8px] whitespace-nowrap rounded-[12px] border border-brand bg-brand px-[16px] py-[10px] font-semibold text-white hover:border-(--brand-700) hover:bg-(--brand-700) disabled:opacity-50"
                wire:click="openLead({{ $lead['id'] }})"
                @disabled(! $lead['purchasable'])
            >
                <x-app.icon name="cart" class="size-[22.5px]" />
                {{ __('marketplace.listing.purchase_short') }}
            </button>
        </div>
    </div>
</article>
