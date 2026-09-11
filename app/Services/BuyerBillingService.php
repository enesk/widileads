<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\LeadState;
use App\Constants\PurchaseStatus;
use App\Constants\WalletTransactionType;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\WalletTransaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Monatsabrechnung eines Kaeufers (FB-059, auf das Wallet umgestellt mit
 * LP-WALLET-022).
 *
 * Gezaehlt wird nach dem heutigen Zustand der gekauften Leads: verkauft (noch
 * offen), erreicht (abgerechnet), unerreichbar und ungueltig (gutgeschrieben).
 * Die Summe ist der Umsatz aus den Kaufbelegen des Monats.
 *
 * **Geld fliesst hier nicht.** Bezahlt wird im Voraus aus dem Wallet, und die
 * Rechnung dazu stellt SaaSykit beim Aufladen aus. Diese Uebersicht rechnet
 * also nichts ab, sie legt Rechenschaft ab: Was hat der Kaeufer bekommen, was
 * ist daraus geworden, und wie hat sich sein Wallet bewegt. Alle Geldwerte sind
 * Betraege in Cent -- Stueckzahlen von Guthaben gibt es nicht mehr, weil der
 * Leadpreis je Verkaeufer verschieden ist.
 *
 * **Die Probe.** Neben den Buchungen steht die Gegenrechnung aus den
 * Kaufbelegen: Die Summe der abgebuchten Betraege muss der Summe der
 * Kaufpreise entsprechen, die im selben Zeitraum abgerechnet wurden. Weichen
 * beide voneinander ab, ist das Wallet und die Kaufstrecke auseinandergelaufen.
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
     *     captured_cents: int,
     *     refunded_cents: int,
     *     topped_up_cents: int,
     *     captured_expected_cents: int,
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
            'captured_cents' => $this->walletSum($buyer, WalletTransactionType::CAPTURE, $from, $until),
            'refunded_cents' => $this->walletSum($buyer, WalletTransactionType::REFUND, $from, $until),
            'topped_up_cents' => $this->walletSum($buyer, WalletTransactionType::TOPUP, $from, $until),
            'captured_expected_cents' => $this->capturedPurchaseCents($buyer, $from, $until),
            'invoices' => $this->invoicesFor($buyer, $from, $until),
        ];
    }

    /**
     * Bewegtes Geld einer Buchungsart im Zeitraum, als absoluter Betrag in Cent.
     *
     * Absolut, weil die Uebersicht die Richtung ueber die Beschriftung sagt:
     * `capture` steht im Ledger mit negativem Vorzeichen, in der Zeile
     * "abgebucht" waere ein Minus doppelt gemoppelt.
     */
    private function walletSum(Tenant $buyer, WalletTransactionType $type, Carbon $from, Carbon $until): int
    {
        $walletId = $buyer->buyerWallet()->value('id');

        if ($walletId === null) {
            return 0;
        }

        $sum = WalletTransaction::query()
            ->where('wallet_id', $walletId)
            ->where('type', $type->value)
            ->whereBetween('created_at', [$from, $until])
            ->sum('amount_cents');

        return abs((int) $sum);
    }

    /**
     * Die Gegenrechnung zu den `capture`-Buchungen: Summe der Kaufpreise, die
     * im Zeitraum abgerechnet wurden.
     *
     * Massgeblich ist `captured_at`, nicht `purchased_at` -- ein Lead kann in
     * einem Monat gekauft und erst im naechsten erreicht und damit abgebucht
     * werden. Verglichen wird mit dem Geld, das im Zeitraum geflossen ist.
     *
     * Erstattete Kaeufe zaehlen mit: Abgebucht wurden sie trotzdem, die
     * Erstattung steht als eigene Zeile daneben. Liesse man sie weg, meldete
     * die Probe nach jeder anerkannten Reklamation eine Abweichung.
     */
    private function capturedPurchaseCents(Tenant $buyer, Carbon $from, Carbon $until): int
    {
        return (int) LeadPurchase::query()
            ->where('buyer_tenant_id', $buyer->getKey())
            ->whereIn('status', [PurchaseStatus::CAPTURED->value, PurchaseStatus::REFUNDED->value])
            ->whereBetween('captured_at', [$from, $until])
            ->sum('price_cents');
    }

    /**
     * Die Rechnungen des Monats -- also die Wallet-Aufladungen.
     *
     * Ausgestellt hat sie SaaSykit beim Checkout; hier werden sie nur
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
