{{--
    Die Lead-Karte (LP-ONBOARD-020).

    Eine Karte, zwei Fassungen: Im Marktplatz steht sie mit Kaufknopf und
    oeffnet das Kaufblatt, im Onboarding (#11) und in der Tour (#13) steht
    dasselbe Markup ohne Livewire-Komponente und ohne Datensatz als Beispiel.
    Vorher lag sie als Partial unter livewire/portal/partials -- damit war sie
    an die Marktplatz-Komponente gebunden, und der Abschluss-Screen haette eine
    Kopie gebraucht, die sofort auseinanderlaeuft.

        <x-app.lead-card
            :lead="$lead"
            :price-hint="$surchargeHint"
            :title-attributes="['wire:click' => 'openLead('.$lead['id'].')']"
            wire:key="lead-{{ $lead['id'] }}"
        >
            <x-slot:action>
                <button type="button" class="btn-primary" wire:click="openLead({{ $lead['id'] }})">...</button>
            </x-slot:action>
        </x-app.lead-card>

    Die Komponente kennt kein Livewire: Der Klick auf den Namen kommt als
    titleAttributes herein, der Kaufknopf als Slot. Ohne beides ist die Karte
    reine Anzeige -- genau das braucht die Variante "demo".

    Markierungspunkte fuer die Tour stehen nicht hier: Was an der Karte haengt
    (data-tour="..."), kommt ueber $attributes am Aufruf an; was am Kaufknopf
    haengt, steht im Slot.

    Die Variante "demo" kennt dazu drei optionale Slots fuer Hinweis-Marker
    (x-ui.tour-marker auf dem Abschluss-Screen). Jeder steht direkt hinter dem
    Element, das er erklaert -- daneben gestellt verloere er den Bezug, auch
    fuer die Vorlesesoftware:

        markerChips  letztes Kind der Merkmals-Zeile
        markerPhone  hinter der Rufnummer
        markerPrice  neben dem Betrag im Preisblock

    In "marketplace" werden sie nicht gerendert.

    Erwartet in $lead (alle Werte fertig formatiert, Kontaktdaten aus dem
    LeadPresenter): name, funnel_name, funnel_icon, postal_code, chips und
    optional portal, company, price, created_at, created_at_exact, is_new,
    purchasable. Die Variante "demo" bringt zusaetzlich phone (maskiert) und
    phone_hint mit -- **ob** maskiert wird, entscheidet allein der
    LeadPresenter, hier steht dazu keine Bedingung.

    Masse und Farben kommen aus den Tokens in portal.css (.card/.card-interactive,
    --radius-card, --radius-control, .pill, Schriftstufen der Tailwind-Skala);
    der Kaufknopf im Slot traegt .btn-primary und damit --control-height. In
    "marketplace" bleibt der Umbruch bei 900 Pixeln: Darunter rutscht die rechte
    Spalte als Zeile unter die Karte. "demo" steht immer so -- sie steht nie in
    voller Breite (auf dem Abschluss-Screen in einer halben Spalte von
    max-w-4xl), und die Media-Query misst den Viewport, nicht den Container.
--}}
@props([
    'lead',
    'variant' => 'marketplace',
    'priceHint' => null,
    'titleAttributes' => [],
])

@php
    if (! in_array($variant, ['marketplace', 'demo'], true)) {
        throw new InvalidArgumentException('x-app.lead-card kennt nur die Varianten "marketplace" und "demo".');
    }

    $isDemo = $variant === 'demo';

    /* Der Name traegt im Marktplatz die Flaeche der ganzen Karte
       (after:inset-0) und ist dort ein Knopf. In der Demo-Fassung gibt es
       nichts zu oeffnen -- dort steht derselbe Text ohne Klickflaeche. */
    $titleBag = new Illuminate\View\ComponentAttributeBag($titleAttributes);

    $hasAction = ! $isDemo && isset($action) && ! $action->isEmpty();

    /* Die Marker gehoeren zur Demo-Fassung. Im Marktplatz bleiben sie aussen
       vor, auch wenn jemand einen Slot mitgibt. */
    $hasMarkerChips = $isDemo && isset($markerChips) && ! $markerChips->isEmpty();
    $hasMarkerPhone = $isDemo && isset($markerPhone) && ! $markerPhone->isEmpty();
    $hasMarkerPrice = $isDemo && isset($markerPrice) && ! $markerPrice->isEmpty();
@endphp

<article
    {{ $attributes->class([
        'relative grid items-start gap-x-4 gap-y-2 p-4',
        /* Drei Spalten nur im Marktplatz, und dort erst ab 900 Pixeln. Die
           Demo-Karte traegt rechts nur den Preis -- und steht in einer schmalen
           Spalte, von der die Media-Query nichts weiss. */
        'grid-cols-[2.25rem_1fr_auto] max-[900px]:grid-cols-[2.25rem_1fr]' => ! $isDemo,
        'grid-cols-[2.25rem_1fr]' => $isDemo,
        /* Die Demo-Karte ist nicht anfassbar -- dort waere ein Rand, der auf
           den Zeiger reagiert, ein Versprechen, das sie nicht haelt. */
        'card' => $isDemo,
        'card-interactive' => ! $isDemo,
    ]) }}
>
    <div class="grid size-9 place-items-center rounded-(--radius-control) bg-brand-50 text-brand">
        <x-app.icon :name="$lead['funnel_icon']" class="size-5" />
    </div>

    <div class="min-w-0">
        @if ($isDemo)
            {{-- Die Karte sagt selbst, dass sie kein echter Lead ist. Grau und
                 nicht in der Markenfarbe: Sie soll nicht nach Auszeichnung
                 aussehen. --}}
            <p class="mb-1 flex">
                <span class="pill">{{ __('portal.onboarding.lead_card.example') }}</span>
            </p>
        @endif

        @if (! $isDemo)
            <button
                type="button"
                class="block max-w-full truncate text-left text-base font-semibold leading-snug text-zinc-900 after:absolute after:inset-0 after:rounded-(--radius-card)"
                {{ $titleBag }}
            >{{ $lead['name'] }}</button>
        @else
            <p class="max-w-full truncate text-base font-semibold leading-snug text-zinc-900">{{ $lead['name'] }}</p>
        @endif

        <div class="mt-0.5 text-sm text-zinc-500">
            <span class="font-medium text-zinc-700">{{ $lead['funnel_name'] }}</span>
            @if (($lead['portal'] ?? null) !== null)
                · {{ $lead['portal'] }}
            @endif
            @if (($lead['company'] ?? null) !== null)
                · {{ __('marketplace.listing.for_company') }} <span class="text-brand">{{ $lead['company'] }}</span>
            @endif
            · {{ __('marketplace.listing.postal_code_short') }} {{ $lead['postal_code'] }}
        </div>

        @if (($lead['chips'] ?? []) !== [])
            <div @class(['mt-2 flex flex-wrap gap-1', 'items-center' => $hasMarkerChips])>
                @foreach ($lead['chips'] as $chip)
                    <span class="max-w-full truncate rounded-md bg-zinc-100 px-2 py-0.5 text-xs text-zinc-700" title="{{ $chip }}">{{ $chip }}</span>
                @endforeach

                @if ($hasMarkerChips)
                    {{ $markerChips }}
                @endif
            </div>
        @endif

        @if ($isDemo && ($lead['phone'] ?? null) !== null)
            {{-- Die verdeckte Rufnummer (Gestaltungsleitfaden 3.5): Schloss,
                 Wert in tabular-nums, darunter der Satz, wann sie frei wird.
                 Beides kommt fertig herein. flex-wrap, damit ein Marker auf
                 360 Pixeln hinter der Nummer umbrechen darf statt sie zu
                 quetschen. --}}
            <p class="mt-2 flex flex-wrap items-center gap-1.5 text-sm text-zinc-700">
                <x-app.icon name="lock" class="size-4 text-zinc-400" />
                <span class="tabular-nums">{{ $lead['phone'] }}</span>

                @if ($hasMarkerPhone)
                    {{ $markerPhone }}
                @endif
            </p>

            @if (($lead['phone_hint'] ?? null) !== null)
                <p class="mt-0.5 text-xs text-zinc-500">{{ $lead['phone_hint'] }}</p>
            @endif
        @endif
    </div>

    <div @class([
        'flex flex-col items-end justify-between gap-2.5 self-stretch',
        'max-[900px]:col-span-full max-[900px]:flex-row max-[900px]:items-center' => ! $isDemo,
        'col-span-full flex-row items-center' => $isDemo,
    ])>
        @if (($lead['created_at'] ?? null) !== null || ($lead['is_new'] ?? false))
            <div class="flex items-center gap-2 whitespace-nowrap text-xs text-zinc-500">
                @if ($lead['is_new'] ?? false)
                    <span class="rounded-full bg-brand px-2 py-0.5 text-xs font-semibold text-white">{{ __('marketplace.listing.badge_new') }}</span>
                @endif
                @if (($lead['created_at'] ?? null) !== null)
                    <span title="{{ $lead['created_at_exact'] ?? '' }}">{{ $lead['created_at'] }}</span>
                @endif
            </div>
        @endif

        @if (($lead['price'] ?? null) !== null || $hasAction || $hasMarkerPrice)
            {{-- Preis und Kaufknopf liegen ueber der Kartenflaeche (z-10),
                 sonst faengt der Name den Klick ab. --}}
            <div class="relative z-10 flex items-center gap-4">
                {{-- Der Marker steht dicht am Betrag und nicht im Abstand zum
                     Kaufknopf -- er erklaert den Preis. Ohne Marker bleibt der
                     Betrag ohne Huelle, damit sich "marketplace" nicht
                     aendert. --}}
                @if ($hasMarkerPrice)
                    <span class="flex items-center gap-1.5">
                        @if (($lead['price'] ?? null) !== null)
                            <span class="text-base font-semibold text-zinc-900 tabular-nums"
                                @if ($priceHint !== null) title="{{ $priceHint }}" @endif
                            >{{ $lead['price'] }}</span>
                        @endif

                        {{ $markerPrice }}
                    </span>
                @elseif (($lead['price'] ?? null) !== null)
                    <span class="text-base font-semibold text-zinc-900 tabular-nums"
                        @if ($priceHint !== null) title="{{ $priceHint }}" @endif
                    >{{ $lead['price'] }}</span>
                @endif

                @if ($hasAction)
                    {{ $action }}
                @endif
            </div>
        @endif
    </div>
</article>
