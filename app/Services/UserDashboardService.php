<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;

class UserDashboardService
{
    public function getUserDashboardUrl(User $user): string
    {
        $tenant = $this->defaultTenantOf($user);

        if ($tenant !== null) {
            return route('filament.dashboard.pages.dashboard', ['tenant' => $tenant]);
        }

        return route('home');
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
