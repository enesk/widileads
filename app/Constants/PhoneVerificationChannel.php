<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Der Weg, auf dem die Rufnummer eines Nutzers bestaetigt wurde
 * (LP-ONBOARD-004).
 *
 * Der Case-Wert steht so in `users.phone_verification_channel`.
 *
 * Seit Ticket #97 entsteht ausschliesslich `call`: Twilio ruft die Nummer an
 * und laesst den angesagten Code am Telefon eintippen. `sms` bleibt stehen,
 * weil es im Bestand steht -- der Support muss sehen koennen, auf welchem Weg
 * eine alte Bestaetigung entstand, und ein entfernter Case liesse das Lesen
 * dieser Zeilen scheitern.
 */
enum PhoneVerificationChannel: string
{
    case SMS = 'sms';

    case CALL = 'call';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $channel): string => $channel->value, self::cases());
    }
}
