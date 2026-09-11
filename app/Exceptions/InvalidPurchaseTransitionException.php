<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Constants\PurchaseStatus;
use App\Models\LeadPurchase;
use RuntimeException;

/**
 * Ein Leadkauf sollte einen Zustandswechsel machen, den seine Zustandsmaschine
 * nicht kennt (LP-WALLET-006).
 *
 * Die Geldseite eines Kaufs kennt genau vier Staende (PurchaseStatus) und drei
 * Wege: reserviert -> abgebucht, reserviert -> aufgeloest, abgebucht ->
 * erstattet. Alles andere ist ein Fehler im Aufrufer, kein Fall fuer eine
 * stille Korrektur: Wer eine bereits abgebuchte Reservierung aufloesen will,
 * wuerde Geld doppelt bewegen -- der Ledger bliebe zwar buchhalterisch
 * richtig, der Saldo des Kaeufers aber falsch.
 *
 * Nicht geworfen wird bei einer Wiederholung, die den Kauf ohnehin schon im
 * Zielzustand findet: Ein zweiter Lauf desselben Listeners darf nicht
 * abbrechen, sondern gibt den bestehenden Stand zurueck (PurchaseService).
 */
class InvalidPurchaseTransitionException extends RuntimeException
{
    public static function for(LeadPurchase $purchase, PurchaseStatus $expected, string $operation): self
    {
        return new self(sprintf(
            'Der Leadkauf #%s steht auf "%s"; "%s" ist nur aus "%s" heraus moeglich.',
            (string) $purchase->getKey(),
            $purchase->status->value,
            $operation,
            $expected->value,
        ));
    }
}
