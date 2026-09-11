{{-- LP-WALLET-011: Guthabenstand des Kaeufers.

     Gross steht das verfuegbare Guthaben -- nur das kann ausgegeben werden.
     Der reservierte Teil steht klein darunter und nur dann, wenn es ihn gibt:
     eine Zeile "davon reserviert: 0,00 €" erklaert nichts. --}}
<div @class([
    'flex flex-col gap-1',
    'rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10' => ! $compact,
])>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('marketplace.wallet.buyer.balance.available_label') }}
    </p>

    <p @class([
        'text-2xl font-bold tracking-tight tabular-nums sm:text-3xl',
        'text-gray-950 dark:text-white' => $availableCents > 0,
        'text-danger-600 dark:text-danger-400' => $availableCents <= 0,
    ])>
        {{ $this->money($availableCents) }}
    </p>

    @if ($reservedCents > 0)
        <p
            class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400"
            title="{{ __('marketplace.wallet.buyer.balance.reserved_tooltip') }}"
        >
            <x-filament::icon
                icon="heroicon-m-information-circle"
                class="h-4 w-4 flex-none text-gray-400"
            />
            {{ __('marketplace.wallet.buyer.balance.reserved_hint', ['amount' => $this->money($reservedCents)]) }}
        </p>

        {{-- Auf dem Telefon zeigt kein Browser ein title-Attribut an, deshalb
             steht die Erklaerung in der breiten Fassung ausgeschrieben. --}}
        @unless ($compact)
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                {{ __('marketplace.wallet.buyer.balance.reserved_tooltip') }}
            </p>
        @endunless
    @endif
</div>
