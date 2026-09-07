<x-filament-panels::page>
    {{-- FB-090: Gekaufte Leads als Filament-Tabelle. Kontaktdaten stehen hier
         im Klartext, weil der Kaufbeleg existiert -- entschieden wird das im
         LeadContactResolver, nicht in dieser Ansicht. --}}
    {{ $this->table }}
</x-filament-panels::page>
