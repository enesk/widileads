<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\PurchaseLead;
use App\Constants\LeadState;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;

/**
 * Der Kauf ist freigeschaltet (FB-054).
 *
 * Loest LeadPurchaseUnavailable ab. Die Zusage bleibt duenn: Sie beantwortet
 * der Oberflaeche, ob ein Knopf sinnvoll ist, und reicht den Kauf an die Action
 * weiter. Die verbindliche Pruefung -- Reservierung, Guthaben, Zustand --
 * steht in PurchaseLead und laeuft dort unter Sperre. Was hier geprueft wird,
 * ist nur eine Vorschau darauf; zwischen Anzeige und Klick kann sich alles
 * geaendert haben.
 */
class LeadPurchaseThroughAction implements LeadPurchaseAction
{
    public function __construct(
        private readonly PurchaseLead $purchaseLead,
        private readonly TenantTypeService $tenantTypes,
    ) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function canPurchase(Tenant $buyer, Lead $lead): bool
    {
        return $lead->lead_state === LeadState::VERFUEGBAR
            && (int) $lead->tenant_id !== (int) $buyer->getKey()
            && $this->tenantTypes->canAccessMarketplace($buyer);
    }

    public function purchase(Tenant $buyer, Lead $lead, ?User $actor = null, ?int $priceShownCents = null): LeadPurchase
    {
        return $this->purchaseLead->handle($buyer, $lead, $actor, $priceShownCents);
    }

    /**
     * Der heute gueltige Preis dieses Leads in Cent -- das, was der Marktplatz
     * anzeigt und beim Kauf zurueckschickt.
     */
    public function priceCentsOf(Lead $lead): int
    {
        return $this->purchaseLead->currentPriceCentsOf($lead);
    }
}
