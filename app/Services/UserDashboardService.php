<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;

class UserDashboardService
{
    /**
     * Wohin ein angemeldeter Nutzer gehoert.
     *
     * Seit dem Umzug des Nutzerbereichs ist das fuer Kaeufer das Portal. Fuer
     * einen Betreiber bleibt es das Filament-Panel unter /dashboard: Das
     * Portal ist der Kaeuferbereich, seine Seiten verlangen den Marktplatz
     * (Gate `marketplace.access`) und wuerden einen Verkaeufer gleich nach der
     * Anmeldung in ein 403 laufen lassen. Die Verkaeuferseiten des Portals
     * sind noch Platzhalter -- sobald sie stehen, faellt diese Weiche weg.
     *
     * An der Methode haengen Anmeldung, Einladungen und die Route /dashboard.
     */
    public function getUserDashboardUrl(User $user): string
    {
        $tenant = $this->defaultTenantOf($user);

        if ($tenant === null) {
            return route('home');
        }

        if (app(TenantTypeService::class)->canAccessMarketplace($tenant)) {
            return route('portal.overview', ['tenant' => $tenant->uuid]);
        }

        return route('filament.dashboard.pages.dashboard', ['tenant' => $tenant->uuid]);
    }

    /**
     * Einstiegsadresse des Portals (Portal Phase 1).
     *
     * Der Mandant steckt im Pfad, deshalb braucht /portal einen Umweg ueber
     * den Standard-Workspace des Nutzers -- dieselbe Auswahl, die auch das
     * Dashboard trifft. Ohne Workspace bleibt nur die Startseite.
     */
    public function getUserPortalUrl(User $user): string
    {
        $tenant = $this->defaultTenantOf($user);

        if ($tenant !== null) {
            return route('portal.overview', ['tenant' => $tenant->uuid]);
        }

        return route('home');
    }

    private function defaultTenantOf(User $user): ?Tenant
    {
        return $user->tenants()->orderByPivot('is_default', 'desc')->first();
    }
}
