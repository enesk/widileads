<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Ein Lead mit Kaufbeleg wird nicht geloescht (LP-WALLET-013).
 *
 * Der Kaufbeleg ist der Beleg der Geldseite: Er traegt Reservierung, Abbuchung,
 * Provision und Verkaeuferanteil. Verschwindet er, bleibt im append-only
 * Journal (LP-WALLET-004) eine Buchung ohne Gegenstueck zurueck -- bei einer
 * offenen Reservierung heisst das dauerhaft geblocktes Geld in
 * `wallets.reserved_cents` (Ticket #78).
 *
 * Wer den Lead wirklich loeschen muss, loest die Reservierung vorher ueber
 * App\Services\Wallet\PurchaseService::release() auf. Abgebuchte Kaeufe bleiben
 * als Beleg stehen; fuer die Aufbewahrungsfrist anonymisiert der
 * LeadRetentionService statt zu loeschen.
 */
class LeadHasPurchasesException extends RuntimeException
{
    public static function forOpenReservation(int $leadId, int $purchaseId): self
    {
        return new self(__('funnel.lead.errors.delete_blocked_by_reservation', [
            'lead' => $leadId,
            'purchase' => $purchaseId,
        ]));
    }

    public static function forPurchases(int $leadId, int $count): self
    {
        return new self(__('funnel.lead.errors.delete_blocked_by_purchase', [
            'lead' => $leadId,
            'count' => $count,
        ]));
    }
}
