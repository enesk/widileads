<x-filament-panels::page>
    {{-- Ein gekaufter Lead. Aufgebaut wie die Lead-Detailseite des Betreibers,
         damit derselbe Lead auf beiden Seiten gleich aussieht. Kontaktdaten
         stehen hier im Klartext, weil der Kaufbeleg existiert -- entschieden
         wird das im LeadContactResolver, nicht in dieser Ansicht. --}}
    {{ $this->leadInfolist }}
</x-filament-panels::page>
