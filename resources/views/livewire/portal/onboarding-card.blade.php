{{--
    Huelle der Aktivierungskarte (LP-ONBOARD-012).

    Livewire braucht ein einzelnes Wurzelelement, auch wenn nichts zu zeigen
    ist: Verschwindet es, verliert die Komponente ihren Platz im Baum und das
    "Ausblenden" haette nichts mehr, woran es haengt. Deshalb traegt die Wurzel
    den Abstand nach oben nur dann, wenn wirklich eine Karte darin steht --
    sonst klaffte im Dashboard eine Luecke ohne Inhalt. Das Markup selbst steht in
    x-app.onboarding-card -- es wird auch vom Entwurf und von der Abnahme ohne
    Livewire gerendert.
--}}
<div @class(['mt-6' => $visible])>
    @if ($visible)
        <x-app.onboarding-card
            :items="$items"
            :done="$done"
            :total="$total"
            :hideable="$hideable"
            :activated="$activated"
        />
    @endif
</div>
