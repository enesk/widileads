<?php

declare(strict_types=1);

namespace App\Observers;

use App\Constants\SettlementStatus;
use App\Models\Settlement;
use App\Services\Wallet\SettlementInvoiceService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Erzeugt den Beleg, sobald ein Postpaid-Einzug bezahlt ist
 * (LP-POSTPAID-015).
 *
 * Bewusst ein Beobachter und kein Aufruf im SettlementService: Der Zustand
 * `paid` entsteht an mehreren Stellen -- durch den Webhook des
 * Zahlungsanbieters (LP-POSTPAID-008), durch den manuellen Einzug des Admins
 * (LP-POSTPAID-012) und moeglicherweise durch eine Korrektur. Ein Beleg, der
 * an nur einem dieser Wege haengt, fehlt an den anderen.
 *
 * Ein Fehler beim Rendern darf den Einzug nicht zurueckdrehen: Das Geld ist
 * eingegangen, und das ist der wichtigere Vorgang. Er wird deshalb
 * protokolliert und nicht geworfen; der Beleg wird beim naechsten Abruf im
 * Portal nachgeholt (SettlementInvoiceService::download()).
 */
class SettlementObserver
{
    public function __construct(private SettlementInvoiceService $invoices) {}

    public function saved(Settlement $settlement): void
    {
        if ($settlement->status !== SettlementStatus::PAID) {
            return;
        }

        if ($settlement->invoice_reference !== null) {
            return;
        }

        try {
            $this->invoices->ensure($settlement);
        } catch (Throwable $exception) {
            Log::warning('Beleg zum Postpaid-Einzug konnte nicht erzeugt werden.', [
                'settlement_id' => $settlement->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
