<x-filament-panels::page>
    @livewire('filament.dashboard.tenant-settings')

    {{-- Der Leadpreis steht hier und nicht auf einer eigenen Seite: Er gehoert
         neben die Bankverbindung, die schon in diesen Einstellungen liegt.
         Nur fuer Verkaeufer -- ein Kaeufer verkauft keine Leads
         (LP-WALLET-012). --}}
    @if ($this->showsLeadPrice())
        @livewire('seller.lead-price-settings')
    @endif
</x-filament-panels::page>
