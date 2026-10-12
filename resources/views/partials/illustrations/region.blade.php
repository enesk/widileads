{{--
    Deutschland-Umriss mit Pin und Umkreis. Gehoert zu Schritt 2 des Wizards:
    Region und Radius waehlen.

    Der Umriss ist ein stark vereinfachter Polygonzug aus 25 Grenzpunkten --
    er soll als Deutschland zu erkennen sein, nicht als Landkarte dienen.
    Abgeleitet aus Laengen- und Breitengraden (5,9-15,0 Ost / 47,3-55,1 Nord),
    damit die Proportion stimmt und das Land nicht gestaucht wirkt.

    Die Umrisslinie traegt brand-200 statt zinc-200: Das Land liegt auf der
    hellen Markenflaeche, dort verschwindet eine graue Linie.
--}}
<circle cx="160" cy="120" r="94" fill="var(--ill-brand-100)"/>

<path d="M145 35L157 46L166 55L176 55L184 50L203 42L216 58L220 95L226 118L223 127L190 138L184 141L197 164L203 180L197 201L199 205L186 201L150 205L120 203L122 180L127 171L109 166L102 152L96 129L96 106L111 72L127 67L135 58L134 46Z"
      fill="#fff" stroke="var(--ill-brand-200)" stroke-width="3" stroke-linejoin="round"/>

{{-- Umkreis um den gewaehlten Ort --}}
<circle cx="147" cy="118" r="40" fill="var(--ill-brand)" fill-opacity=".12"/>
<circle cx="147" cy="118" r="40" fill="none" stroke="var(--ill-brand)" stroke-width="2.5" stroke-dasharray="8 7" stroke-linecap="round"/>

{{-- Nachbarorte im Umkreis --}}
<g fill="var(--ill-brand-200)">
    <circle cx="122" cy="104" r="5"/>
    <circle cx="174" cy="112" r="5"/>
    <circle cx="128" cy="142" r="5"/>
    <circle cx="170" cy="140" r="5"/>
</g>

{{-- Der gewaehlte Ort --}}
<path d="M147 90c9 0 16 7 16 16 0 12-16 26-16 26s-16-14-16-26c0-9 7-16 16-16z" fill="var(--ill-brand)"/>
<circle cx="147" cy="106" r="6" fill="#fff"/>
