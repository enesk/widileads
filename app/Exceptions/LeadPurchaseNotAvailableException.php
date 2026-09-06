<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Der Kauf eines Leads ist derzeit nicht moeglich (FB-053).
 *
 * Bis FB-054 den Kaufvorgang baut, gibt es keinen Weg vom Marktplatz zum Kauf.
 * Der Abbruch ist Absicht: Ein Aufruf, der still nichts tut, sieht aus wie ein
 * gelungener Kauf.
 */
class LeadPurchaseNotAvailableException extends RuntimeException
{
    public static function notImplementedYet(): self
    {
        return new self(__('marketplace.listing.purchase_unavailable'));
    }
}
