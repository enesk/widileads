<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Warum ein Kaeufer die Rufnummer eines gekauften Leads sieht -- oder nicht
 * (Ticket #5).
 *
 * Entschieden wird das an genau einer Stelle, der PhoneReleaseRule. Dieser
 * Grund ist deren Begruendung und damit auch die Grundlage fuer den Hinweis
 * im Portal: Das Portal soll den Stand nicht nachrechnen, sondern vorlesen.
 */
enum PhoneReleaseReason: string
{
    /** Frei, weil ein Gespraech angenommen wurde und lang genug war. */
    case ANSWERED = 'answered';

    /** Frei, weil genuegend gezaehlte Versuche vorliegen -- auf Zeit. */
    case ATTEMPTS = 'attempts';

    /** Frei, weil der Lead abgerechnet wird. Endgueltig. */
    case BILLABLE = 'billable';

    /**
     * Wieder verdeckt, weil der Lead als nicht erreichbar geschlossen wurde
     * und der Kaeufer eine Gutschrift bekommt: entweder Geld oder Nummer.
     *
     * Dieser Grund hebt eine Freigabe nach ATTEMPTS ausdruecklich auf.
     */
    case REVOKED_CREDIT = 'revoked_credit';

    /** Noch verdeckt: kein angenommenes Gespraech, zu wenige Versuche. */
    case LOCKED = 'locked';

    /**
     * Gibt dieser Grund die Rufnummer frei?
     */
    public function releasesPhone(): bool
    {
        return $this === self::ANSWERED
            || $this === self::ATTEMPTS
            || $this === self::BILLABLE;
    }
}
