{{--
    Ein Hinweis-Marker am Element (LP-ONBOARD-011).

    <x-ui.tour-marker id="phone" />

    Der kleine Punkt mit dem Fragezeichen, der neben einem Element steht und
    dazu einen Satz zeigt. Gebraucht auf dem Abschluss-Screen des Onboardings
    an drei Stellen der Beispielkarte.

    **Er zeigt den Satz nicht selbst.** Der Marker setzt nur `hint` im
    umgebenden x-data; welcher Satz dazu erscheint und wo, entscheidet die
    Seite. So laeuft auf 360 Pixeln keine Sprechblase aus dem Bildschirm, und
    alle drei Marker zeigen ueber aria-controls auf denselben Bereich.

    Voraussetzung im Markup: ein Elternteil mit `x-data="{ hint: null }"` und
    ein Bereich mit der Kennung aus `region` (Vorgabe: demo-lead-hint).

    Das inline-Geschwister der gefuehrten Tour (LP-ONBOARD-012): Dort fuehrt ein
    Spotlight eine Folge von Schritten ueber die Seite, hier steht ein Hinweis
    an einer Stelle. Wenn tour.js steht, zieht der Marker dort ein.
--}}
@props([
    'id',
    'region' => 'demo-lead-hint',
])

<button
    type="button"
    {{-- Sichtbar ist ein Punkt von 1,75 rem; die Klickflaeche reicht ueber
         before:-inset-2 darueber hinaus und erreicht die 44 Pixel, die sich auf
         dem Telefon treffen lassen. --}}
    class="bg-brand-50 text-brand relative grid size-7 shrink-0 place-items-center rounded-full border border-(--brand-200) text-xs font-bold transition-colors before:absolute before:-inset-2 before:content-[''] hover:bg-(--brand-100)"
    x-on:click="hint = hint === @js($id) ? null : @js($id)"
    x-bind:aria-expanded="hint === @js($id) ? 'true' : 'false'"
    aria-expanded="false"
    aria-controls="{{ $region }}"
>
    <span aria-hidden="true">?</span>
    <span class="sr-only">{{ __('portal.onboarding.done.markers.'.$id.'.label') }}</span>
</button>
