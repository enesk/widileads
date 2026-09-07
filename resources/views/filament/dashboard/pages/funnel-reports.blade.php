<x-filament-panels::page>
    {{-- FB-090: Trichter je Funnel mit Filament-Komponenten. Alle Zahlen kommen
         als Aggregate aus dem FunnelConversionReport, nicht aus einer Schleife
         ueber Ereignisse. --}}
    {{ $this->filters }}

    {{ $this->summary }}

    {{ $this->table }}
</x-filament-panels::page>
