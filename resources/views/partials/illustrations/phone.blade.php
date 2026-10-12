{{--
    Smartphone mit eingehender Nachricht. Steht am Anfang der Telefon-
    Verifizierung: Hier kommt der Code an.

    Nur der Inhalt des <svg> -- Rahmen, viewBox und Beschriftung setzt
    <x-ui.illustration>.

    Weisse Flaechen tragen eine Linie in zinc-200: Ohne sie verschwindet eine
    weisse Karte auf weissem Grund, und genau dort stehen die Bilder im
    Onboarding.
--}}
<circle cx="160" cy="120" r="94" fill="var(--ill-brand-100)"/>

{{-- Geraet --}}
<rect x="148" y="22" width="96" height="196" rx="22" fill="#fff" stroke="var(--ill-zinc-200)" stroke-width="2.5"/>
<rect x="156" y="30" width="80" height="180" rx="15" fill="var(--ill-brand-700)"/>
<rect x="182" y="38" width="28" height="5" rx="2.5" fill="#fff" fill-opacity=".25"/>
<rect x="176" y="66" width="40" height="30" rx="7" fill="#fff"/>
<path d="M182 75l14 10 14-10" stroke="var(--ill-brand)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
<rect x="166" y="116" width="60" height="7" rx="3.5" fill="#fff"/>
<rect x="166" y="130" width="42" height="5" rx="2.5" fill="#fff" fill-opacity=".5"/>

{{-- Die Nachricht selbst, als Karte vor dem Geraet. Blase und Zipfel sind ein
     Pfad, sonst liefe die Linie quer durch die Karte. --}}
<path d="M42 62H138A14 14 0 01152 76V106A14 14 0 01138 120H66L44 136L46 120H42A14 14 0 0128 106V76A14 14 0 0142 62Z"
      fill="#fff" stroke="var(--ill-zinc-200)" stroke-width="2.5" stroke-linejoin="round"/>
<circle cx="58" cy="91" r="14" fill="var(--ill-brand-100)"/>
<circle cx="58" cy="87" r="5" fill="var(--ill-brand)"/>
<path d="M50 98c3-5 13-5 16 0" stroke="var(--ill-brand)" stroke-width="2.5" stroke-linecap="round" fill="none"/>
<rect x="82" y="80" width="56" height="7" rx="3.5" fill="var(--ill-brand)"/>
<rect x="82" y="94" width="36" height="6" rx="3" fill="var(--ill-zinc-200)"/>

{{-- Zaehler am Geraet --}}
<circle cx="242" cy="34" r="14" fill="var(--ill-brand)"/>
<path d="M235 28h14a3 3 0 013 3v6a3 3 0 01-3 3h-6l-5 4v-4h-3a3 3 0 01-3-3v-6a3 3 0 013-3z" fill="#fff"/>
