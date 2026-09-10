<x-filament-panels::page>
    {{-- Ein gekaufter Lead. Aufgebaut wie die Lead-Detailseite des Betreibers,
         damit derselbe Lead auf beiden Seiten gleich aussieht. Kontaktdaten
         stehen hier im Klartext, weil der Kaufbeleg existiert -- entschieden
         wird das im LeadContactResolver, nicht in dieser Ansicht. --}}

    {{-- Anrufen und Versuchsverlauf als eigene Livewire-Komponente: Sie fragt
         waehrend eines laufenden Anrufs alle paar Sekunden nach und soll dabei
         nicht die ganze Seite mitziehen (FB-084). Weiter gereicht wird nur der
         Schluessel des Kaufbelegs. --}}
    @livewire('buyer.lead-call-panel', ['purchaseId' => $this->purchaseId], key('lead-call-panel-'.$this->purchaseId))

    {{ $this->leadInfolist }}
</x-filament-panels::page>
