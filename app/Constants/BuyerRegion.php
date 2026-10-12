<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Woher die Anfragen eines Kaeufers kommen duerfen (LP-ONBOARD-009).
 *
 * Zwei Faelle und kein dritter: ganz Deutschland oder ein Umkreis um eine
 * Postleitzahl. Mehrere Gebiete, Bundeslaender oder eine Liste von
 * Postleitzahlen gibt es hier ausdruecklich nicht -- dafuer stehen in den
 * Kaufkriterien die `postal_prefixes`, die der Kaeufer von Hand pflegt.
 */
enum BuyerRegion: string
{
    case NATIONWIDE = 'nationwide';

    case RADIUS = 'radius';

    public static function fromValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::NATIONWIDE;
    }
}
