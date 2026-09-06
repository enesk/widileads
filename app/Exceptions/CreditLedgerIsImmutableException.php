<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Buchungen des Guthabenkontos sind nach dem Anlegen unveraenderlich (FB-052).
 *
 * Das Journal ist der Beleg dafuer, wie ein Saldo zustande kam -- es traegt
 * Kaeufe, Abbuchungen, Gutschriften und Korrekturen. Waere es nachtraeglich
 * aenderbar, waere jede Abrechnung angreifbar und der Saldo nicht mehr
 * herleitbar. Analog zum Audit-Log (FB-005) und zum Zustandsprotokoll (FB-030)
 * brechen Aenderungs- und Loeschversuche deshalb hart ab: Eine Fehlbuchung wird
 * durch eine Gegenbuchung korrigiert, nicht durch Ueberschreiben.
 */
class CreditLedgerIsImmutableException extends RuntimeException
{
    public static function forUpdate(): self
    {
        return new self(__('marketplace.credit.errors.not_updatable'));
    }

    public static function forDeletion(): self
    {
        return new self(__('marketplace.credit.errors.not_deletable'));
    }
}
