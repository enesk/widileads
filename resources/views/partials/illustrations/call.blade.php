{{--
    Hoerer mit Stoppuhr. Erklaert die Abrechnung: Erst ein angenommener Anruf
    kostet Geld, ein verpasster nicht.

    Drei Zustaende ueber data-state am <svg> (ringing | answered | missed).
    Was in welchem Zustand sichtbar ist, entscheidet data-when; die Farbe des
    Hoerers und des Zeigers kommt aus illustrations.css. Im Bild steht kein
    Skript und kein einziger Farbwert.
--}}
<circle cx="160" cy="120" r="94" fill="var(--ill-brand-100)"/>

{{-- Hoerer --}}
<g transform="translate(56 64) scale(4)">
    <path class="ill-handset" fill="var(--ill-brand)" d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>
</g>

{{-- Es klingelt --}}
<g data-when="ringing" fill="none" stroke="var(--ill-brand)" stroke-width="5" stroke-linecap="round">
    <path class="ill-wave" d="M149 72A26 26 0 0 1 166 92"/>
    <path class="ill-wave ill-wave-2" d="M153 60A38 38 0 0 1 177 89"/>
    <path class="ill-wave ill-wave-3" d="M157 49A50 50 0 0 1 189 87"/>
</g>

{{-- Angenommen: Haken. Verpasst: durchgestrichener Hoerer. --}}
<g data-when="answered">
    <circle cx="150" cy="160" r="22" fill="var(--ill-brand)" stroke="#fff" stroke-width="4"/>
    <path d="M141 160l7 7 12-14" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
</g>
<g data-when="missed">
    <circle cx="150" cy="160" r="22" fill="var(--ill-zinc-400)" stroke="#fff" stroke-width="4"/>
    <path d="M142 152l16 16M158 152l-16 16" stroke="#fff" stroke-width="4" stroke-linecap="round" fill="none"/>
</g>

{{-- Stoppuhr --}}
<rect x="224" y="98" width="16" height="12" rx="5" fill="var(--ill-brand-700)"/>
<circle cx="232" cy="150" r="42" fill="#fff"/>
<g stroke="var(--ill-zinc-200)" stroke-width="4" stroke-linecap="round">
    <path d="M232 116v6"/>
    <path d="M260 150h-6"/>
    <path d="M232 184v-6"/>
    <path d="M204 150h6"/>
</g>
<path class="ill-hand" d="M232 150v-26" stroke="var(--ill-brand)" stroke-width="5" stroke-linecap="round" fill="none"/>
<circle cx="232" cy="150" r="5" fill="var(--ill-brand)"/>
