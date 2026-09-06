<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lead;
use App\Models\Tenant;

/**
 * Vorgabeumsetzung, bis FB-054 den Kaufvorgang baut (FB-032).
 *
 * Antwortet immer mit nein. Das ist die sichere Richtung: Solange niemand
 * kaufen kann, darf auch niemand Klartext-Kontaktdaten sehen.
 */
class NoLeadPurchases implements LeadPurchaseLookup
{
    public function hasPurchased(Tenant $tenant, Lead $lead): bool
    {
        return false;
    }
}
