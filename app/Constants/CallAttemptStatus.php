<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Stand eines Anrufversuchs (FB-082).
 *
 * Die Werte folgen den Anrufstaenden von Twilio, damit ein Rueckruf ohne
 * Umdeutung gespeichert werden kann. Bewertet wird hier nichts -- ob ein
 * Versuch als "erreicht" zaehlt, entscheidet FB-083 an genau einer Stelle
 * anhand der Gespraechsdauer.
 */
enum CallAttemptStatus: string
{
    /** Bei Twilio angelegt, noch nicht gewaehlt. */
    case QUEUED = 'queued';

    /** Es klingelt beim Mitarbeiter oder beim Lead. */
    case RINGING = 'ringing';

    /** Verbindung steht. */
    case IN_PROGRESS = 'in_progress';

    /** Gespraech beendet -- sagt fuer sich genommen nichts ueber die Dauer. */
    case COMPLETED = 'completed';

    /** Niemand hat abgenommen. */
    case NO_ANSWER = 'no_answer';

    /** Besetzt. */
    case BUSY = 'busy';

    /** Technisch gescheitert. */
    case FAILED = 'failed';

    /** Vor dem Verbinden abgebrochen. */
    case CANCELED = 'canceled';

    /**
     * Der Stand, wie Twilio ihn meldet. Unbekannte Werte gelten als
     * gescheitert: Ein Stand, den wir nicht kennen, darf nicht als Erfolg
     * durchgehen.
     */
    public static function fromProvider(string $status): self
    {
        return match ($status) {
            'queued', 'initiated' => self::QUEUED,
            'ringing' => self::RINGING,
            'in-progress', 'answered' => self::IN_PROGRESS,
            'completed' => self::COMPLETED,
            'no-answer' => self::NO_ANSWER,
            'busy' => self::BUSY,
            'canceled' => self::CANCELED,
            default => self::FAILED,
        };
    }

    /**
     * Ist der Versuch abgeschlossen? Danach aendert kein Rueckruf mehr etwas.
     */
    public function isFinished(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::NO_ANSWER,
            self::BUSY,
            self::FAILED,
            self::CANCELED,
        ], true);
    }

    public function label(): string
    {
        return __('call.attempt.status.'.$this->value);
    }
}
