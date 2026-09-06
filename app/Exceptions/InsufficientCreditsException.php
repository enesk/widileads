<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Das Guthaben reicht fuer diese Abbuchung nicht (FB-052).
 *
 * Der Saldo darf nie negativ werden -- ein Kaeufer soll nicht auf Kredit
 * kaufen koennen. Geprueft wird das innerhalb der Buchungstransaktion unter
 * Sperre, damit auch zwei gleichzeitige Kaeufe nicht gemeinsam ins Minus
 * laufen (die Nebenlaeufigkeitspruefung des Kaufvorgangs selbst gehoert zu
 * FB-054).
 */
class InsufficientCreditsException extends RuntimeException
{
    public static function for(int $balance, int $requested): self
    {
        return new self(__('marketplace.credit.errors.insufficient', [
            'balance' => $balance,
            'requested' => $requested,
        ]));
    }
}
