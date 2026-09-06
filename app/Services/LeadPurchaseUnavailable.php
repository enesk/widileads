<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\LeadPurchaseNotAvailableException;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;

/**
 * Vorgabeumsetzung bis FB-054: Es kann noch nicht gekauft werden (FB-053).
 *
 * Sie sagt nein und tut nichts. Der Marktplatz zeigt den Kaufknopf deshalb
 * inaktiv -- sichtbar, damit die Stelle steht, aber ohne Wirkung. Ein Aufruf
 * von purchase() bricht ab, statt still nichts zu tun: Ein Kauf, der scheinbar
 * gelingt, waere schlimmer als gar keiner.
 */
class LeadPurchaseUnavailable implements LeadPurchaseAction
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function canPurchase(Tenant $buyer, Lead $lead): bool
    {
        return false;
    }

    public function purchase(Tenant $buyer, Lead $lead, User $actor): void
    {
        throw LeadPurchaseNotAvailableException::notImplementedYet();
    }
}
