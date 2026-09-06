<x-filament-panels::page>
    {{-- Die Filament-Page ist nur die Huelle fuer Routing und Navigation des
         Dashboard-Panels. Der Inhalt ist reines Livewire (siehe Master-Prompt:
         kein Filament im Tenant-Dashboard). --}}
    @livewire('dashboard.buyer-profile')
</x-filament-panels::page>
