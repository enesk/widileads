<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Dieser Lead kann von diesem Kaeufer nicht (mehr) gekauft werden (FB-054).
 *
 * Der haeufigste Fall ist harmlos und alltaeglich: Zwei Kaeufer klicken
 * gleichzeitig, einer gewinnt. Die Ausnahme ist deshalb kein Fehler, sondern
 * eine Auskunft -- die Oberflaeche zeigt sie als Hinweis, nicht als Absturz.
 */
class LeadNotPurchasableException extends RuntimeException
{
    public static function alreadyTaken(?Throwable $previous = null): self
    {
        return new self(__('marketplace.purchase.errors.already_taken'), 0, $previous);
    }

    public static function buyerNotApproved(): self
    {
        return new self(__('marketplace.purchase.errors.buyer_not_approved'));
    }

    public static function alreadyBought(): self
    {
        return new self(__('marketplace.purchase.errors.already_bought'));
    }

    public static function ownLead(): self
    {
        return new self(__('marketplace.purchase.errors.own_lead'));
    }
}
