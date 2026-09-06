<x-filament-panels::page>
    {{-- Huelle: Routing, Navigation, Zugriff. Der Inhalt ist reines Livewire
         (siehe agent-prompt.md, Abschnitt 5). --}}
    @livewire('dashboard.funnel-builder', ['funnel' => $this->funnel])
</x-filament-panels::page>
