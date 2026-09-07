<x-filament-panels::page>
    {{-- FB-090: Marktplatzliste als Filament-Tabelle. Kontaktdaten stehen dort
         ausschliesslich als LeadPresenter-Werte -- bereits fertig maskiert
         (FB-032). Weder hier noch in der Tabelle wird entschieden, was ein
         Kaeufer sehen darf. --}}
    {{ $this->table }}
</x-filament-panels::page>
