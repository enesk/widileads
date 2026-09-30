<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;

class UserDashboardService
{
    /**
     * Wohin ein angemeldeter Nutzer gehoert.
     *
     * Seit dem Umzug des Nutzerbereichs ist das immer das Portal und nicht
     * mehr das Filament-Panel unter /dashboard. Die Methode bleibt bestehen,
     * weil an ihr die Anmeldung, die Einladungen und die Route /dashboard
     * haengen -- sie zeigt nur woanders hin.
     */
    public function getUserDashboardUrl(User $user): string
    {
        return $this->getUserPortalUrl($user);
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
