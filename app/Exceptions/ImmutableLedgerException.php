<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Buchungen des Wallet-Ledgers sind nach dem Anlegen unveraenderlich
 * (LP-WALLET-004).
 *
 * Das Journal ist der einzige Beleg dafuer, wie ein Geldsaldo zustande kam --
 * Aufladungen, Reservierungen, Abbuchungen, Provisionen, Auszahlungen. Waere es
 * nachtraeglich aenderbar, waere jede Abrechnung angreifbar und kein Saldo mehr
 * herleitbar. Gleiches Muster wie beim Guthabenkonto (FB-052), beim Audit-Log
 * (FB-005) und beim Zustandsprotokoll (FB-030): Eine Fehlbuchung wird durch eine
 * Gegenbuchung korrigiert (WalletTransactionType::ADJUSTMENT), nicht durch
 * Ueberschreiben.
 */
class ImmutableLedgerException extends RuntimeException
{
    public static function forUpdate(): self
    {
        return new self(__('marketplace.wallet.errors.not_updatable'));
    }

    public static function forDeletion(): self
    {
        return new self(__('marketplace.wallet.errors.not_deletable'));
    }
}
