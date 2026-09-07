<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;

/**
 * Beantwortet aus `lead_purchases`, ob ein Kaeufer einen Lead gekauft hat
 * (FB-054).
 *
 * Loest NoLeadPurchases ab, das bis zum Kaufvorgang immer nein sagen musste.
 * Damit liefert LeadContactResolver::for() einem Kaeufer nach dem Kauf die
 * Kontaktdaten im Klartext -- ohne dass an der Maskierlogik selbst etwas
 * geaendert werden musste. Genau dafuer war die Zusage austauschbar angelegt
 * (FB-032).
 */
class RecordedLeadPurchases implements LeadPurchaseLookup
{
    public function hasPurchased(Tenant $tenant, Lead $lead): bool
    {
        if ($lead->getKey() === null) {
            return false;
        }

        return LeadPurchase::query()
            ->where('lead_id', $lead->getKey())
            ->where('buyer_tenant_id', $tenant->getKey())
            ->exists();
    }
}
