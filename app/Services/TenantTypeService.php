<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\TenantType;
use App\Models\Tenant;
use Filament\Facades\Filament;

/**
 * Beantwortet, was ein Tenant aufgrund seines Typs darf (FB-002).
 *
 * Der Typ ist die einzige Quelle dieser Entscheidung: Betreiber verwalten
 * Funnels, Kaeufer nutzen den Marktplatz. Die Gates "funnels.manage" und
 * "marketplace.access" (siehe AuthServiceProvider) delegieren hierher, damit
 * Policies, Middleware und Navigation dieselbe Regel verwenden.
 */
class TenantTypeService
{
    /**
     * Der aktuell aktive Tenant, oder null ausserhalb eines Tenant-Kontexts.
     */
    public function currentTenant(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    public function canManageFunnels(?Tenant $tenant): bool
    {
        return $this->typeOf($tenant)?->canManageFunnels() ?? false;
    }

    public function canAccessMarketplace(?Tenant $tenant): bool
    {
        return $this->typeOf($tenant)?->canAccessMarketplace() ?? false;
    }

    public function isOperator(?Tenant $tenant): bool
    {
        return $this->typeOf($tenant) === TenantType::OPERATOR;
    }

    public function isBuyer(?Tenant $tenant): bool
    {
        return $this->typeOf($tenant) === TenantType::BUYER;
    }

    private function typeOf(?Tenant $tenant): ?TenantType
    {
        return $tenant?->type;
    }
}
