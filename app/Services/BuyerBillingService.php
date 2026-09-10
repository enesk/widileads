<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\CreditLedgerType;
use App\Constants\LeadState;
use App\Models\CreditLedgerEntry;
use App\Models\LeadPurchase;
use App\Models\Scopes\TenantScopes;
use App\Models\Tenant;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Monatsabrechnung eines Kaeufers (FB-059).
 *
 * Gezaehlt wird nach dem heutigen Zustand der gekauften Leads: verkauft (noch
 * offen), erreicht (abgerechnet), unerreichbar und ungueltig (gutgeschrieben).
 * Die Summe ist der Umsatz aus den Kaufbelegen des Monats.
 *
 * **Geld fliesst hier nicht.** Bezahlt wird im Voraus per Guthaben
 * (Entscheidung 1 vom 2026-09-06), und die Rechnung dazu stellt SaaSykit beim
 * Kauf des Guthabenpakets aus. Diese Uebersicht rechnet also nichts ab, sie
 * legt Rechenschaft ab: Was hat der Kaeufer bekommen, was ist daraus geworden,
 * und wie hat sich sein Guthaben bewegt. Deshalb steht neben den Leadzahlen
 * immer auch die Guthabenbewegung -- weichen beide voneinander ab, stimmt
 * etwas nicht.
 */
class BuyerBillingService
{
    /**
     * Die Abrechnung eines Kaeufers fuer einen Monat.
     *
     * @return array{
     *     month: string,
     *     from: Carbon,
     *     until: Carbon,
     *     purchases: int,
     *     revenue_cents: int,
     *     currency: string,
     *     states: array<string, int>,
     *     credits_debited: int,
     *     credits_refunded: int,
     *     credits_purchased: int,
     *     invoices: list<array{transaction_uuid: string, amount: int, created_at: string|null}>
     * }
     */
    public function statementFor(Tenant $buyer, CarbonInterface $month): array
    {
        $from = Carbon::instance($month)->startOfMonth();
        $until = Carbon::instance($month)->endOfMonth();

        $purchases = LeadPurchase::query()
            ->where('buyer_tenant_id', $buyer->getKey())
            ->whereBetween('purchased_at', [$from, $until])
            ->with('lead')
            ->get();

        $states = [];

        foreach (LeadState::cases() as $state) {
            $states[$state->value] = 0;
        }

        foreach ($purchases as $purchase) {
            $state = $purchase->lead?->lead_state;

            if ($state !== null) {
                $states[$state->value]++;
            }
        }

        return [
            'month' => $from->format('Y-m'),
            'from' => $from,
            'until' => $until,
            'purchases' => $purchases->count(),
            'revenue_cents' => (int) $purchases->sum('price_cents'),
            'currency' => (string) ($purchases->first()->currency ?? strtoupper((string) config('app.default_currency'))),
            'states' => $states,
            'credits_debited' => $this->creditsOfType($buyer, CreditLedgerType::DEBIT, $from, $until),
            'credits_refunded' => $this->creditsOfType($buyer, CreditLedgerType::REFUND, $from, $until),
            'credits_purchased' => $this->creditsOfType($buyer, CreditLedgerType::PURCHASE, $from, $until),
            'invoices' => $this->invoicesFor($buyer, $from, $until),
        ];
    }

    /**
     * Bewegte Guthaben einer Buchungsart im Zeitraum, als absolute Zahl.
     */
    private function creditsOfType(Tenant $buyer, CreditLedgerType $type, Carbon $from, Carbon $until): int
    {
        $sum = CreditLedgerEntry::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('tenant_id', $buyer->getKey())
            ->where('type', $type->value)
            ->whereBetween('created_at', [$from, $until])
            ->sum('credits');

        return abs((int) $sum);
    }

    /**
     * Die Rechnungen des Monats -- also die Guthabenkaeufe.
     *
     * Ausgestellt hat sie SaaSykit beim Kauf des Pakets; hier werden sie nur
     * verknuepft. Eine zweite Rechnungsstellung daneben waere ein zweiter
     * Zahlungsweg, und den soll es nicht geben.
     *
     * @return list<array{transaction_uuid: string, amount: int, created_at: string|null}>
     */
    private function invoicesFor(Tenant $buyer, Carbon $from, Carbon $until): array
    {
        return Transaction::query()
            ->whereHas('order', static fn ($order) => $order->where('tenant_id', $buyer->getKey()))
            ->whereBetween('created_at', [$from, $until])
            ->get()
            ->map(static fn (Transaction $transaction): array => [
                'transaction_uuid' => (string) $transaction->uuid,
                'amount' => (int) $transaction->amount,
                'created_at' => $transaction->created_at?->toDateString(),
            ])
            ->all();
    }
}
