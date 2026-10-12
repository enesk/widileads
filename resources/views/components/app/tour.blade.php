{{--
    Die Tour-Konfiguration einer Portalseite (LP-ONBOARD-013).

    Ein verstecktes Element, kein sichtbares Markup: Es sagt dem Skript
    (resources/js/modules/tour.js), welche Tour auf dieser Seite laeuft, ob sie
    von selbst starten soll, wohin ihr Abschluss gemeldet wird und wie die
    Knoepfe heissen. Die Schritte selbst stehen als data-tour-Marken an den
    Elementen, die sie erklaeren.

    Steht einmal im Rahmen (x-layouts.portal-app) und nicht je Seite: Welche
    Seite eine Tour hat, entscheidet App\Support\PortalTour anhand des
    Routennamens -- so kann keine Seite eine Tour zeigen, die niemand abhakt.

    Gerendert wird gar nichts, wenn es keinen Mandanten gibt, die Seite keine
    Tour hat oder es kein Kaeufer-Workspace ist. Ein Verkaeufer hat keinen
    Marktplatz.

    Erwartet: $tenant (Workspace), $onboarding (Fortschritt oder null, kommt vom
    OnboardingComposer am Rahmen).
--}}
@props(['tenant' => null, 'onboarding' => null])

@php
    use App\Support\PortalTour;

    $tour = $tenant instanceof \App\Models\Tenant && $tenant->isBuyer()
        ? PortalTour::forRouteName(request()->route()?->getName())
        : null;

    $step = $tour === null ? null : PortalTour::step($tour);

    /*
     | Von selbst startet die Tour nur beim ersten Besuch -- also solange ihr
     | Haken fehlt. Ohne Fortschritt (kein Kaeufer, kein Mandant im Pfad)
     | startet sie nie von selbst; ueber ?tour=... und das Kontomenue bleibt sie
     | erreichbar.
     */
    $auto = $step !== null
        && $onboarding instanceof \App\Services\Onboarding\OnboardingProgress
        && ! $onboarding->isCompleted($step);
@endphp

@if ($tour !== null)
    <div
        hidden
        data-tour-config
        data-tour-name="{{ $tour }}"
        data-tour-steps="{{ PortalTour::markerCount($tour) }}"
        data-tour-seen-url="{{ route('portal.onboarding.tour.seen', ['tenant' => $tenant->uuid, 'name' => $tour]) }}"
        data-tour-next="{{ __('tour.controls.next') }}"
        data-tour-previous="{{ __('tour.controls.previous') }}"
        data-tour-done="{{ __('tour.controls.done') }}"
        data-tour-close="{{ __('tour.controls.close') }}"
        data-tour-progress="{{ __('tour.controls.progress') }}"
        @if ($auto) data-tour-auto @endif
    ></div>
@endif
