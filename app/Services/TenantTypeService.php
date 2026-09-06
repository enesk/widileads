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

    /**
     * Darf dieser Mandant den Lead-Marktplatz nutzen?
     *
     * Zwei Bedingungen, beide notwendig: der Typ muss `buyer` sein (FB-002) und
     * die Registrierung freigeschaltet (FB-050). Der Typ allein genuegt nicht --
     * ein Kaeufer entsteht durch Selbstregistrierung, und bis der
     * Plattform-Admin entschieden hat, kommt er an keinen Lead.
     *
     * Die Regel steht bewusst nur hier: Gate, Middleware und Navigation fragen
     * dieselbe Methode, damit die Sperre nicht an einer Stelle wirkt und an
     * einer anderen fehlt.
     */
    public function canAccessMarketplace(?Tenant $tenant): bool
    {
        if (! ($this->typeOf($tenant)?->canAccessMarketplace() ?? false)) {
            return false;
        }

        return $tenant?->isApprovedBuyer() ?? false;
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
