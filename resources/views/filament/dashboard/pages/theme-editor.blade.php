<x-filament-panels::page>
    {{-- Huelle: Routing, Navigation, Zugriff. Inhalt ist reines Livewire. --}}
    @livewire('dashboard.theme-editor', ['funnel' => $this->funnel])
</x-filament-panels::page>
