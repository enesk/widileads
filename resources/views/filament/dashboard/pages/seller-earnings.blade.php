{{-- LP-WALLET-012: Einnahmen des Verkaeufers. Die Seite haelt nur die beiden
     Komponenten zusammen; gerechnet und gebucht wird in ihnen. --}}
<x-filament-panels::page>
    @livewire('seller.seller-wallet')

    @livewire('seller.payout-request-form')
</x-filament-panels::page>
