<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Stand eines hinterlegten Zahlungsmittels (LP-POSTPAID-005).
 *
 * Ein Zahlungsmittel wird nie geloescht, sondern nur stillgelegt: Settlements
 * verweisen darauf, und der Beleg eines Einzugs muss auch Jahre spaeter noch
 * sagen koennen, womit abgebucht wurde. Deshalb gibt es neben `active` zwei
 * Endstaende -- vom Kaeufer oder von der Bank widerrufen (`revoked`) und
 * dauerhaft gescheitert (`failed`).
 *
 * Der Wert eines Case steht so in der Datenbank (payment_methods.status) und
 * darf nach dem ersten Einsatz nicht mehr geaendert werden.
 */
enum PaymentMethodStatus: string
{
    /** Einsatzbereit: damit darf off-session eingezogen werden. */
    case ACTIVE = 'active';

    /** Mandat oder Zahlungsmittel widerrufen -- kein Einzug mehr moeglich. */
    case REVOKED = 'revoked';

    /** Einzug dauerhaft gescheitert (Karte abgelehnt, Konto erloschen). */
    case FAILED = 'failed';

    /**
     * Darf mit diesem Zahlungsmittel noch eingezogen werden?
     */
    public function isUsable(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
