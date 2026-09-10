<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Stand der Rufnummern-Bestaetigung eines Kaeufer-Mitarbeiters (FB-080).
 *
 * Nur eine Nummer im Stand `verified` darf als Rufnummernanzeige beim
 * Endkunden erscheinen. Jeder andere Stand bedeutet: kein Anruf unter dieser
 * Nummer.
 */
enum CallerIdStatus: string
{
    /** Bestaetigung angefordert, Twilio hat den Code angesagt, noch nicht bestaetigt. */
    case PENDING = 'pending';

    /** Von Twilio bestaetigt, als Rufnummernanzeige zugelassen. */
    case VERIFIED = 'verified';

    /** Die Bestaetigung lief ab, bevor sie abgeschlossen wurde. */
    case EXPIRED = 'expired';

    /** Twilio hat die Bestaetigung abgelehnt oder der Anruf scheiterte. */
    case FAILED = 'failed';

    public function isVerified(): bool
    {
        return $this === self::VERIFIED;
    }

    public function label(): string
    {
        return __('call.caller_id.status.'.$this->value);
    }
}
