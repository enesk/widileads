<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schickt jede Dashboard-Seite, die es im Portal fertig gibt, dorthin weiter.
 *
 * Der Nutzerbereich ist das Portal unter /portal; das Filament-Panel unter
 * /dashboard haelt nur noch, was dort noch nicht nachgebaut ist. Ohne diese
 * Weiterleitung landet ein Nutzer trotzdem im Panel -- ueber ein altes
 * Lesezeichen, eine alte E-Mail oder einen Verweis, den wir uebersehen haben.
 *
 * Bewusst nur die Seiten mit einem FERTIGEN Gegenstueck: Die Verkaeuferseiten
 * des Portals (Funnels, Einnahmen, Auszahlung, Berichte, Einstellungen) sind
 * noch Platzhalter, und die Zwei-Faktor-Anmeldung, die Abonnements und die
 * Benutzerverwaltung gibt es im Portal gar nicht. Wer sie hierher umleitet,
 * nimmt dem Nutzer eine Funktion weg, statt ihn umzuziehen.
 *
 * Die Weiterleitung steht am ENDE der Panel-Middleware: Filament ist dann
 * bereits gebootet, Mandant und Panel-Scope stehen. Entschieden wird im Portal
 * ein zweites Mal -- jede Portalseite prueft ihren Zugang selbst.
 */
class RedirectDashboardToPortal
{
    /**
     * Seiten des Kaeuferbereichs. Umgeleitet wird nur, wer den Marktplatz
     * ueberhaupt sehen darf: Ein Verkaeufer-Mandant liefe sonst von seiner
     * Startseite in ein 403 des Portals.
     *
     * @var array<string, string>
     */
    private const MOVED_FOR_BUYERS = [
        'filament.dashboard.pages.dashboard' => 'portal.overview',
        'filament.dashboard.pages.marketplace' => 'portal.marketplace',
        'filament.dashboard.pages.purchased-leads' => 'portal.leads',
        'filament.dashboard.pages.guthaben' => 'portal.wallet',
        'filament.dashboard.pages.buyer-profile' => 'portal.buying-criteria',
        'filament.dashboard.pages.caller-id' => 'portal.caller-id',
        'filament.dashboard.resources.transactions.index' => 'portal.transactions',
        'filament.dashboard.resources.orders.index' => 'portal.orders',
    ];

    /**
     * Seiten, die jedem Mandanten offenstehen.
     *
     * @var array<string, string>
     */
    private const MOVED_FOR_ALL = [
        'filament.dashboard.pages.my-profile' => 'portal.profile',
    ];

    /** Der gekaufte Lead -- die einzige umgezogene Seite mit eigenem Parameter. */
    private const MOVED_PURCHASE = 'filament.dashboard.pages.purchased-leads.{purchase}';

    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName();
        $tenant = $this->tenant($request);

        if ($name === null || ! $tenant instanceof Tenant) {
            return $next($request);
        }

        $target = self::MOVED_FOR_ALL[$name] ?? null;

        if ($target !== null) {
            return redirect()->route($target, ['tenant' => $tenant->uuid]);
        }

        if (! Gate::allows('marketplace.access', $tenant)) {
            return $next($request);
        }

        if ($name === self::MOVED_PURCHASE) {
            $purchase = $request->route('purchase');

            return $purchase === null
                ? $next($request)
                : redirect()->route('portal.leads.show', [
                    'tenant' => $tenant->uuid,
                    'purchase' => $purchase,
                ]);
        }

        $target = self::MOVED_FOR_BUYERS[$name] ?? null;

        return $target === null
            ? $next($request)
            : redirect()->route($target, ['tenant' => $tenant->uuid]);
    }

    /**
     * Der Mandant aus dem Pfad -- je nach Zeitpunkt der Modellbindung das
     * Modell selbst oder noch die UUID als Text.
     */
    private function tenant(Request $request): ?Tenant
    {
        $tenant = $request->route('tenant');

        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        if (! is_string($tenant) || $tenant === '') {
            return null;
        }

        return Tenant::query()->withoutGlobalScopes()->where('uuid', $tenant)->first();
    }
}
