<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Stand der Rufnummern-Bestaetigung eines Kaeufer-Mitarbeiters (FB-080).
 *
 * Nur eine Nummer im Stand `verified` darf als Rufnummernanzeige beim
 * Endkunden erscheinen. Jeder andere Stand bedeutet: kein Anruf unter dieser
 * Nummer.
 *
 * Seit Ticket #97 laeuft die Bestaetigung ausschliesslich ueber den
 * Bestaetigungsanruf. Der kennt Fehlerfaelle, die der SMS-Code nicht hatte --
 * das Telefon wird nicht abgenommen, oder der Anruf kommt zustande, ohne dass
 * jemand den Code eintippt. Beides braucht einen eigenen Stand: Ein Nutzer,
 * dem die Seite weiter "Bestaetigung laeuft" zeigt, wartet auf etwas, das
 * nicht mehr kommt.
 *
 * **Was sich aus Twilios Rueckmeldung NICHT lesen laesst:** Ob ein
 * Anrufbeantworter abgenommen, der Nutzer aufgelegt oder einen falschen Code
 * eingetippt hat. Der Statuscallback eines Validierungsanrufs liefert
 * `VerificationStatus` (success/failed) und `CallStatus`; eine
 * Anrufbeantworter-Erkennung gibt es dafuer nicht. Diese drei Faelle fallen
 * deshalb zusammen in `not_confirmed` -- lieber ein Stand, der stimmt, als
 * drei, die geraten sind.
 */
enum CallerIdStatus: string
{
    /** Bestaetigung angefordert, Twilio hat den Code angesagt, noch nicht bestaetigt. */
    case PENDING = 'pending';

    /** Von Twilio bestaetigt, als Rufnummernanzeige zugelassen. */
    case VERIFIED = 'verified';

    /** Die Bestaetigung lief ab, bevor sie abgeschlossen wurde. */
    case EXPIRED = 'expired';

    /** Niemand hat abgenommen: keine Antwort, besetzt oder abgebrochen. */
    case NO_ANSWER = 'no_answer';

    /**
     * Der Anruf kam zustande, der Code wurde aber nicht bestaetigt --
     * Anrufbeantworter, falscher Code oder aufgelegt.
     */
    case NOT_CONFIRMED = 'not_confirmed';

    /** Twilio hat die Bestaetigung abgelehnt oder der Anruf scheiterte technisch. */
    case FAILED = 'failed';

    public function isVerified(): bool
    {
        return $this === self::VERIFIED;
    }

    /**
     * Ein Stand, aus dem heraus neu begonnen werden muss.
     *
     * Die Oberflaeche braucht genau diese Unterscheidung: `pending` heisst
     * warten, `verified` heisst fertig, alles andere heisst "noch einmal, und
     * hier steht warum".
     */
    public function isFailure(): bool
    {
        return match ($this) {
            self::PENDING, self::VERIFIED => false,
            default => true,
        };
    }

    public function label(): string
    {
        return __('call.caller_id.status.'.$this->value);
    }

    /** Die Erklaerung fuer den Nutzer, warum es nicht geklappt hat. */
    public function failureHint(): ?string
    {
        return $this->isFailure() ? __('call.caller_id.hint.'.$this->value) : null;
    }
}
