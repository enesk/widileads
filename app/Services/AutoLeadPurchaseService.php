<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\PurchaseLead;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\LeadNotPurchasableException;
use App\Marketplace\MarketplaceListing;
use App\Models\BuyerProfile;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Scopes\TenantScopes;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Kauft passende Leads von sich aus (FB-056).
 *
 * Fuer jedes Kaufprofil mit aktivem Autokauf: die Leads holen, die der
 * Marktplatz diesem Kaeufer ohnehin zeigen wuerde, und sie kaufen, bis das
 * Tageslimit erreicht ist. Es wird ausdruecklich derselbe Weg genommen wie bei
 * einem Klick -- dieselbe Auswahl (MarketplaceListing samt LeadMatcher) und
 * dieselbe Kaufaktion (PurchaseLead). Eine zweite Auswahl- oder Kauflogik
 * wuerde frueher oder spaeter etwas anderes tun als die Anzeige, und der
 * Kaeufer bekaeme Leads, die er im Marktplatz nie gesehen hat.
 *
 * Zwei Ausgaenge sind Alltag und kein Fehler: Ein anderer war schneller
 * (LeadNotPurchasableException) -- weitermachen mit dem naechsten. Das Guthaben
 * ist alle (InsufficientFundsException) -- fuer diesen Kaeufer aufhoeren, die
 * uebrigen laufen weiter.
 */
class AutoLeadPurchaseService
{
    public function __construct(
        private readonly MarketplaceListing $listing,
        private readonly PurchaseLead $purchaseLead,
    ) {}

    /**
     * Laesst alle Kaufprofile mit aktivem Autokauf laufen.
     *
     * @return int Zahl der gekauften Leads
     */
    public function run(): int
    {
        $bought = 0;

        $profiles = BuyerProfile::query()
            // Der Lauf gehoert keinem Mandanten -- er bedient alle.
            ->withoutGlobalScopes(TenantScopes::names())
            ->autoBuying()
            ->with('tenant')
            ->orderBy('id')
            ->get();

        foreach ($profiles as $profile) {
            $bought += $this->runFor($profile);
        }

        return $bought;
    }

    /**
     * Kauft fuer ein einzelnes Kaufprofil.
     */
    private function runFor(BuyerProfile $profile): int
    {
        $buyer = $profile->tenant;

        if (! $buyer instanceof Tenant || ! $buyer->isApprovedBuyer()) {
            return 0;
        }

        $remaining = $this->remainingToday($profile, $buyer);

        if ($remaining <= 0) {
            return 0;
        }

        $bought = 0;

        foreach ($this->listing->for($buyer, $profile) as $lead) {
            if ($bought >= $remaining) {
                break;
            }

            if (! $lead instanceof Lead) {
                continue;
            }

            try {
                $this->purchaseLead->handle($buyer, $lead);
                $bought++;
            } catch (LeadNotPurchasableException) {
                // Ein anderer war schneller oder der Lead ist inzwischen
                // vergeben -- der naechste ist dran.
                continue;
            } catch (InsufficientFundsException) {
                // Fuer diesen Kaeufer ist Schluss. Es waere sinnlos, die
                // restlichen Leads durchzuprobieren, und jeder Versuch
                // reservierte den Lead kurz und gaebe ihn wieder frei.
                Log::info('Autokauf gestoppt: Guthaben aufgebraucht.', [
                    'tenant_id' => $buyer->getKey(),
                    'bought' => $bought,
                ]);

                break;
            }
        }

        return $bought;
    }

    /**
     * Wie viele Leads dieser Kaeufer heute noch bekommen darf.
     *
     * Gezaehlt werden alle Kaeufe des Tages, nicht nur die automatischen: Ein
     * Tageslimit, das der Kaeufer durch eigene Klicks umgehen kann, waere
     * keines. Ohne Limit -- null oder 0 -- gilt keine Obergrenze.
     */
    private function remainingToday(BuyerProfile $profile, Tenant $buyer): int
    {
        $limit = (int) ($profile->daily_limit ?? 0);

        if ($limit <= 0) {
            return PHP_INT_MAX;
        }

        $today = LeadPurchase::query()
            ->where('buyer_tenant_id', $buyer->getKey())
            ->whereDate('purchased_at', now()->toDateString())
            ->count();

        return max(0, $limit - $today);
    }
}
