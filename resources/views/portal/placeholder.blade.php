{{--
    Platzhalter einer noch nicht gebauten Portalseite (Portal Phase 1).

    Jede geplante Portalseite hat ab sofort ihre Route und ihren Namen; der
    Inhalt kommt Seite fuer Seite in den spaeteren Phasen. Bis dahin zeigt
    dieser Rahmen, dass die Adresse richtig ist und die Seite noch fehlt --
    besser als ein 404, das nach einem Fehler aussieht.

    Der Rahmen ist der Arbeitsbereich aus dem Entwurf (x-layouts.portal-app)
    und nicht mehr der oeffentliche Portalrahmen: Kopf, Navigation und
    Workspace stehen damit schon, bevor die einzelnen Seiten gebaut sind.

    Erwartete Daten: $title (Sprachschluessel der geplanten Seite).
--}}
<x-layouts.portal-app>

    <x-slot name="title">
        {{ __($title) }}
    </x-slot>

    <p class="text-sm font-medium text-zinc-500">{{ __('portal.label') }}</p>
    <h1 class="mt-2 text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __($title) }}</h1>
    <p class="mt-4 max-w-prose text-zinc-600">
        {{ __('portal.placeholder.text') }}
    </p>

    <x-app.button variant="ghost" :href="route('dashboard')" class="mt-8">
        {{ __('portal.placeholder.link') }}
    </x-app.button>

</x-layouts.portal-app>
