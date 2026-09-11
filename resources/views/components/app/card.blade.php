{{--
    Die weisse Flaeche mit Rahmen (LP-PORTAL-003).

    Form und Farbe stehen als .card in resources/css/portal.css. Hier steht nur
    die Entscheidung zwischen ruhiger und anklickbarer Karte, damit nicht die
    eine Haelfte der Seiten .card und die andere .card-interactive schreibt.
--}}
@props(['interactive' => false, 'as' => 'div'])

<{{ $as }} {{ $attributes->merge(['class' => $interactive ? 'card-interactive' : 'card']) }}>
    {{ $slot }}
</{{ $as }}>
