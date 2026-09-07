<x-filament-panels::page>
    {{-- FB-090: Zeitverlauf mit Filament-Komponenten. Die Reihe kommt als
         Aggregat aus dem LeadTimelineReport. --}}
    {{ $this->filters }}

    {{ $this->table }}
</x-filament-panels::page>
