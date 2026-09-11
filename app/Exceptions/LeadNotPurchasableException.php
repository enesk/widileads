<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Dieser Lead kann von diesem Kaeufer nicht (mehr) gekauft werden (FB-054).
 *
 * Der haeufigste Fall ist harmlos und alltaeglich: Zwei Kaeufer klicken
 * gleichzeitig, einer gewinnt. Die Ausnahme ist deshalb kein Fehler, sondern
 * eine Auskunft -- die Oberflaeche zeigt sie als Hinweis, nicht als Absturz.
 *
 * Der Code der Ausnahme ist der HTTP-Status, mit dem ein Endpunkt denselben
 * Ausgang beantworten wuerde (LP-WALLET-007). Alle Faelle beschreiben einen
 * Konflikt mit dem inzwischen erreichten Stand -- ein anderer war schneller,
 * der Lead ist schon gekauft, der Preis hat sich geaendert --, deshalb 409.
 * Einzige Ausnahme ist der nicht freigeschaltete Kaeufer: Das ist kein
 * Konflikt, sondern eine fehlende Berechtigung.
 */
class LeadNotPurchasableException extends RuntimeException
{
    public static function alreadyTaken(?Throwable $previous = null): self
    {
        return new self(__('marketplace.purchase.errors.already_taken'), Response::HTTP_CONFLICT, $previous);
    }

    public static function buyerNotApproved(): self
    {
        return new self(__('marketplace.purchase.errors.buyer_not_approved'), Response::HTTP_FORBIDDEN);
    }

    public static function alreadyBought(): self
    {
        return new self(__('marketplace.purchase.errors.already_bought'), Response::HTTP_CONFLICT);
    }

    public static function ownLead(): self
    {
        return new self(__('marketplace.purchase.errors.own_lead'), Response::HTTP_CONFLICT);
    }

    /**
     * Der Verkaeufer hat seinen Preis geaendert, waehrend der Kaeufer die
     * Marktplatzseite offen hatte (LP-WALLET-007).
     */
    public static function priceChanged(): self
    {
        return new self(__('marketplace.purchase.errors.price_changed'), Response::HTTP_CONFLICT);
    }

    /**
     * Der HTTP-Status, der zu diesem Ausgang gehoert.
     */
    public function status(): int
    {
        return $this->getCode() > 0 ? (int) $this->getCode() : Response::HTTP_CONFLICT;
    }
}
