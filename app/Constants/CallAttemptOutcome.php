<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Bewertung eines Anrufversuchs (FB-082).
 *
 * Getrennt vom Stand des Versuchs (CallAttemptStatus): Der Stand ist die
 * Meldung von Twilio, die Bewertung ist unsere Auslegung. Berechnet wird sie
 * an genau einer Stelle, dem AttemptClassifier (FB-083); `provider_payload`
 * bleibt dabei unangetastet, damit eine Neubewertung jederzeit moeglich ist.
 *
 * Null in der Spalte heisst: noch nicht bewertet.
 */
enum CallAttemptOutcome: string
{
    /** Gespraech kam zustande und war lang genug -- der Lead ist erreicht. */
    case ANSWERED = 'answered';

    /** Erfolglos, zaehlt aber gegen die Zahl der noetigen Versuche. */
    case FAILED_VALID = 'failed_valid';

    /** Erfolglos und zaehlt nicht (siehe `ignore_reason`). */
    case FAILED_IGNORED = 'failed_ignored';

    /**
     * Zaehlt dieser Versuch fuer das Regelwerk mit?
     */
    public function counts(): bool
    {
        return $this !== self::FAILED_IGNORED;
    }

    public function label(): string
    {
        return __('call.attempt.outcome.'.$this->value);
    }
}
