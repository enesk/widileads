{{-- LP-WALLET-009: Guthaben aufladen. Freier Betrag mit Vorschlaegen; die
     Schaltflaeche uebergibt an den vorhandenen Einmalkauf-Checkout.

     Ein gewoehnliches Formular und kein Livewire-Aufruf: Der Checkout ist eine
     Weiterleitung auf eine andere Strecke, dafuer braucht es keinen
     Zwischenzustand im Browser. Die Vorschlaege setzen nur das Eingabefeld
     (Alpine) -- der Betrag wird serverseitig geprueft. --}}
<x-filament-panels::page>
    @php
        $tenant = $this->currentTenant();
        $minEuro = $this->minEuro();
        $maxEuro = $this->maxEuro();
        $presets = $this->presetsEuro();
        $defaultEuro = old('amount_euro', $presets[0] ?? $minEuro);
    @endphp

    {{-- Guthabenkopf als eigene Livewire-Komponente: Derselbe Stand steht im
         Marktplatz, und nach einer Buchung soll sich genau dieser Teil der
         Seite erneuern -- nicht die ganze Seite. --}}
    @livewire('buyer.wallet-balance')

    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('marketplace.wallet.top_up.description') }}
    </p>

    <x-filament::section :heading="__('marketplace.wallet.buyer.top_up_heading')">
        <form
            method="POST"
            action="{{ route('buyer.wallet.topup') }}"
            x-data="{ amount: '{{ $defaultEuro }}' }"
            class="flex flex-col gap-6"
        >
            @csrf
            <input type="hidden" name="tenant" value="{{ $tenant->uuid }}">

            @if ($presets !== [])
                <div class="flex flex-wrap gap-2">
                    @foreach ($presets as $preset)
                        <button
                            type="button"
                            x-on:click="amount = '{{ $preset }}'"
                            x-bind:class="amount === '{{ $preset }}'
                                ? 'bg-primary-600 text-white border-primary-600'
                                : 'bg-white text-gray-950 border-gray-300 dark:bg-gray-900 dark:text-white dark:border-gray-600'"
                            class="rounded-lg border px-4 py-2 text-sm font-semibold"
                        >
                            {{ number_format($preset, 0, ',', '.') }}&nbsp;&euro;
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="max-w-xs">
                <label for="amount_euro" class="block text-sm font-medium text-gray-950 dark:text-white">
                    {{ __('marketplace.wallet.top_up.amount_label') }}
                </label>

                <div class="mt-1 flex items-center gap-2">
                    <input
                        id="amount_euro"
                        type="number"
                        name="amount_euro"
                        step="1"
                        min="{{ $minEuro }}"
                        max="{{ $maxEuro }}"
                        required
                        x-model="amount"
                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-base text-gray-950 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    >
                    <span class="text-base text-gray-500 dark:text-gray-400">&euro;</span>
                </div>

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('marketplace.wallet.top_up.amount_hint', [
                        'min' => number_format($minEuro, 0, ',', '.'),
                        'max' => number_format($maxEuro, 0, ',', '.'),
                    ]) }}
                </p>

                @error('amount_euro')
                    <p class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-filament::button type="submit">
                    {{ __('marketplace.wallet.top_up.submit') }}
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    <p class="text-xs text-gray-400 dark:text-gray-500">
        {{ __('marketplace.wallet.top_up.payment_hint') }}
    </p>

    {{-- LP-WALLET-011: Der Verlauf. Eigene Komponente mit eigener
         Seitenblaetterung -- ein Blaettern darf das Aufladeformular nicht
         zuruecksetzen. --}}
    <x-filament::section :heading="__('marketplace.wallet.buyer.history.heading')">
        @livewire('buyer.wallet-transactions')
    </x-filament::section>
</x-filament-panels::page>
