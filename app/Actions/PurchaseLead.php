<?php

declare(strict_types=1);

namespace App\Actions;

use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Events\Lead\LeadPurchased;
use App\Exceptions\IllegalLeadTransition;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\LeadNotPurchasableException;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreditLedgerService;
use App\Services\LeadStateService;
use App\Services\TenantTypeService;
use Illuminate\Support\Facades\DB;

/**
 * Der Kauf eines Leads durch einen Kaeufer-Mandanten (FB-054).
 *
 * Der Vorgang laeuft in zwei Schritten, und das ist Absicht.
 *
 * **Erst reservieren, dann kaufen.** Die Reservierung ist eine eigene,
 * abgeschlossene Transaktion. Erst dadurch sieht ein zweiter Kaeufer sofort,
 * dass der Lead vergeben ist -- laege sie in derselben Transaktion wie der
 * Kauf, waere sie bis zum Ende unsichtbar, und zwei gleichzeitige Klicks
 * wuerden beide bis zur Guthabenpruefung laufen. Wer die Reservierung gewinnt,
 * entscheidet die Zustandsmaschine: LeadStateService::transition() sperrt den
 * Lead und weist einen Wechsel ab, dessen Ausgangszustand sich inzwischen
 * geaendert hat. Genau einer gewinnt.
 *
 * **Kauf, Abbuchung und Zustandswechsel in einer Transaktion.** Ein Kaufbeleg
 * ohne Abbuchung waere ein verschenkter Lead, eine Abbuchung ohne Kaufbeleg
 * ein bezahlter, der niemandem gehoert. Beides zusammen mit dem Wechsel nach
 * `verkauft` -- oder gar nichts.
 *
 * **Scheitert der Kauf, wird die Reservierung zurueckgegeben.** Reicht das
 * Guthaben nicht, bliebe der Lead sonst bis zum Ablauf der Frist blockiert,
 * obwohl niemand ihn kaufen wird.
 */
class PurchaseLead
{
    /**
     * Guthaben, das ein Lead kostet.
     *
     * Ein Guthaben ist ein Lead -- so werden die Pakete verkauft ("10 Leads").
     * Das ist keine Stellschraube, sondern die Definition der Einheit, deshalb
     * steht sie hier und nicht in der Konfiguration.
     */
    public const CREDITS_PER_LEAD = 1;

    public function __construct(
        private readonly LeadStateService $states,
        private readonly CreditLedgerService $credits,
        private readonly TenantTypeService $tenantTypes,
    ) {}

    /**
     * @throws LeadNotPurchasableException wenn der Kaeufer oder der Lead nicht in Frage kommt
     * @throws InsufficientCreditsException wenn das Guthaben nicht reicht
     */
    public function handle(Tenant $buyer, Lead $lead, User $actor): LeadPurchase
    {
        $this->guardBuyer($buyer);
        $this->guardLead($buyer, $lead);

        $this->reserve($buyer, $lead, $actor);

        try {
            $purchase = $this->settle($buyer, $lead, $actor);
        } catch (\Throwable $exception) {
            // Ein Lead, den niemand kaufen kann, soll nicht bis zum Ablauf der
            // Frist blockiert bleiben.
            $this->release($lead, $actor);

            throw $exception;
        }

        event(new LeadPurchased($lead, $buyer, $purchase, $actor));

        return $purchase;
    }

    /**
     * Legt den Lead auf diesen Kaeufer fest.
     *
     * Bei zwei gleichzeitigen Klicks kommt genau einer durch; der andere
     * bekommt eine IllegalLeadTransition, weil der Ausgangszustand beim
     * Sperren nicht mehr `verfuegbar` ist.
     *
     * @throws LeadNotPurchasableException wenn ein anderer Kaeufer schneller war
     */
    private function reserve(Tenant $buyer, Lead $lead, User $actor): void
    {
        try {
            DB::transaction(function () use ($buyer, $lead, $actor): void {
                $this->states->transition(
                    $lead,
                    LeadState::RESERVIERT,
                    LeadTransitionReason::RESERVED_BY_BUYER,
                    $actor,
                    ['buyer_tenant_id' => $buyer->getKey()],
                );

                $lead->update([
                    'reserved_by' => $buyer->getKey(),
                    'reserved_until' => now()->addMinutes((int) config('funnel.lead.reservation_ttl')),
                ]);
            });
        } catch (IllegalLeadTransition $exception) {
            throw LeadNotPurchasableException::alreadyTaken($exception);
        }
    }

    /**
     * Kaufbeleg, Abbuchung und Zustandswechsel -- alles oder nichts.
     */
    private function settle(Tenant $buyer, Lead $lead, User $actor): LeadPurchase
    {
        return DB::transaction(function () use ($buyer, $lead, $actor): LeadPurchase {
            // Der Lead ist reserviert; die Sperre haelt ihn bis zum Ende der
            // Transaktion fest.
            $locked = Lead::query()->whereKey($lead->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->lead_state !== LeadState::RESERVIERT
                || (int) $locked->reserved_by !== (int) $buyer->getKey()) {
                throw LeadNotPurchasableException::alreadyTaken();
            }

            $purchase = LeadPurchase::query()->create([
                'lead_id' => $lead->getKey(),
                'buyer_tenant_id' => $buyer->getKey(),
                'price_cents' => $this->priceCentsOf($lead),
                'currency' => strtoupper((string) config('app.default_currency')),
                'purchased_at' => now(),
            ]);

            // Wirft InsufficientCreditsException, wenn das Guthaben nicht
            // reicht -- dann faellt die gesamte Transaktion zurueck, samt
            // Kaufbeleg.
            $this->credits->debit($buyer, self::CREDITS_PER_LEAD, $purchase);

            $maxBuyers = $lead->funnel?->effectiveMaxBuyers() ?? 1;
            $sold = LeadPurchase::query()->where('lead_id', $lead->getKey())->count();
            $slotsLeft = max(0, $maxBuyers - $sold);

            // Im Mehrfachverkauf bleibt der Lead im Angebot, bis die
            // Hoechstzahl erreicht ist (FB-055). Der Grund der Buchung bleibt
            // trotzdem "gekauft" -- es ist ein Kauf, der ihn zurueckgibt, keine
            // aufgegebene Reservierung. Ohne diese Unterscheidung waere im
            // Protokoll spaeter nicht mehr zu sehen, dass an dieser Stelle Geld
            // geflossen ist.
            $this->states->transition(
                $lead,
                $slotsLeft > 0 ? LeadState::VERFUEGBAR : LeadState::VERKAUFT,
                LeadTransitionReason::PURCHASED,
                $actor,
                [
                    'buyer_tenant_id' => $buyer->getKey(),
                    'lead_purchase_id' => $purchase->getKey(),
                    'buyers' => $sold,
                    'max_buyers' => $maxBuyers,
                ],
            );

            // Die Reservierung hat ihren Zweck erfuellt. Bleibt der Lead im
            // Angebot, muss auch der Kaeufer daraus verschwinden -- sonst
            // haengt an einem verfuegbaren Lead ein fremder Name.
            $lead->update($slotsLeft > 0
                ? ['reserved_by' => null, 'reserved_until' => null]
                : ['reserved_until' => null]);

            return $purchase;
        });
    }

    /**
     * Gibt eine Reservierung zurueck, deren Kauf nicht zustande kam.
     */
    private function release(Lead $lead, User $actor): void
    {
        $lead->refresh();

        if ($lead->lead_state !== LeadState::RESERVIERT) {
            return;
        }

        DB::transaction(function () use ($lead, $actor): void {
            $this->states->transition(
                $lead,
                LeadState::VERFUEGBAR,
                LeadTransitionReason::RESERVATION_RELEASED,
                $actor,
            );

            $lead->update(['reserved_by' => null, 'reserved_until' => null]);
        });
    }

    /**
     * Preis in der kleinsten Waehrungseinheit.
     *
     * Massgeblich ist der beim Anlegen des Leads festgehaltene Preis, nicht der
     * heutige Funnelpreis: Der Kaeufer kauft den Lead zu dem Preis, zu dem er
     * ihm angeboten wurde (Architekturleitsatz 4).
     */
    private function priceCentsOf(Lead $lead): int
    {
        $funnel = $lead->funnel;

        // Im Mehrfachverkauf zahlt jeder Kaeufer den Anteilspreis des Funnels
        // (FB-055) -- er bekommt den Lead nicht allein. Der beim Anlegen
        // festgehaltene Preis taugt dafuer nicht: Er stammt aus der Zeit vor
        // FB-055 und traegt den Exklusivpreis.
        if ($funnel !== null && $funnel->sale_mode->isShared()) {
            return (int) round($funnel->effectivePriceForSale() * 100);
        }

        $price = $lead->getAttribute('price_at_creation');

        if (! is_numeric($price)) {
            $price = (float) config('funnel.lead.default_price');
        }

        return (int) round(((float) $price) * 100);
    }

    /**
     * @throws LeadNotPurchasableException
     */
    private function guardBuyer(Tenant $buyer): void
    {
        if (! $this->tenantTypes->canAccessMarketplace($buyer)) {
            throw LeadNotPurchasableException::buyerNotApproved();
        }
    }

    /**
     * @throws LeadNotPurchasableException
     */
    private function guardLead(Tenant $buyer, Lead $lead): void
    {
        if ($lead->lead_state !== LeadState::VERFUEGBAR) {
            throw LeadNotPurchasableException::alreadyTaken();
        }

        if ((int) $lead->tenant_id === (int) $buyer->getKey()) {
            throw LeadNotPurchasableException::ownLead();
        }

        // Im Mehrfachverkauf bleibt der Lead im Angebot -- auch fuer den, der
        // ihn schon hat. Zweimal denselben Lead zu kaufen waere fuer den
        // Kaeufer nichts als eine zweite Abbuchung.
        $alreadyBought = LeadPurchase::query()
            ->where('lead_id', $lead->getKey())
            ->where('buyer_tenant_id', $buyer->getKey())
            ->exists();

        if ($alreadyBought) {
            throw LeadNotPurchasableException::alreadyBought();
        }
    }
}
