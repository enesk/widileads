<x-filament-panels::page>
    {{-- FB-090: Umsatzuebersicht mit Filament-Komponenten. Alle Betraege kommen
         in Cent aus dem OperatorRevenueReport und werden erst hier
         formatiert. --}}
    {{ $this->filters }}

    {{ $this->summary }}

    {{ $this->table }}
</x-filament-panels::page>
