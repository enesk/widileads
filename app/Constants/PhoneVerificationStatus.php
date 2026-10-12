<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Der Stand eines Bestaetigungsversuchs (LP-ONBOARD-004).
 *
 * Die Werte sind die Status, die Twilio Verify selbst kennt (`pending`,
 * `approved`, `canceled`), erweitert um `failed` fuer den Fall, dass Twilio die
 * Anforderung gar nicht angenommen hat. Wir fuehren hier keinen eigenen
 * Lebenszyklus: Die Codes liegen bei Twilio, wir protokollieren nur, was dort
 * passiert ist -- fuer die Rate-Limits und fuer den Support.
 *
 * `failed` ist wichtig fuer die Zaehlung: Ein Versuch, bei dem keine SMS
 * rausging, darf das Stundenlimit der Nummer nicht verbrauchen.
 */
enum PhoneVerificationStatus: string
{
    /** Code ist unterwegs, noch nicht geprueft. */
    case PENDING = 'pending';

    /** Code war richtig -- die Nummer gilt als bestaetigt. */
    case APPROVED = 'approved';

    /** Twilio hat den Vorgang beendet (abgebrochen oder abgelaufen). */
    case CANCELED = 'canceled';

    /** Twilio hat die Anforderung abgelehnt, es ging kein Code raus. */
    case FAILED = 'failed';

    /**
     * Zaehlt dieser Versuch gegen das Limit der Nummer?
     *
     * Nur Versuche, bei denen wirklich ein Code verschickt wurde. Sonst
     * sperrte eine Stoerung bei Twilio den Nutzer fuer eine Stunde aus.
     */
    public function countsTowardsLimit(): bool
    {
        return $this !== self::FAILED;
    }
}
