<?php

declare(strict_types=1);

namespace App\Dto;

use App\Constants\PhoneReleaseReason;

/**
 * Der Stand der Rufnummernfreigabe fuer einen Kaeufer (Ticket #5).
 *
 * Die PhoneReleaseRule gibt bewusst keinen Wahrheitswert zurueck, sondern
 * diesen Stand: Das Portal zeigt neben der verdeckten Nummer an, wie viele
 * Versuche noch fehlen (Ticket #6). Bekaeme es nur "frei ja/nein", muesste es
 * die Versuche selbst zaehlen -- und dann steht im Hinweis irgendwann eine
 * andere Zahl als die, die entscheidet.
 */
final class PhoneRelease
{
    public function __construct(
        public readonly bool $released,
        public readonly int $countedAttempts,
        public readonly int $requiredAttempts,
        public readonly PhoneReleaseReason $reason,
    ) {}

    /**
     * Wie viele gezaehlte Versuche noch fehlen, bis die Nummer herauskommt.
     *
     * Nie negativ und nach einer Freigabe immer 0 -- auch wenn sie ueber ein
     * angenommenes Gespraech oder die Abrechnung kam und die Versuche damit
     * gar keine Rolle spielten.
     */
    public function remainingAttempts(): int
    {
        if ($this->released) {
            return 0;
        }

        return max(0, $this->requiredAttempts - $this->countedAttempts);
    }

    /**
     * Ist die Nummer frei und die Erreichbarkeit damit entschieden?
     * (Ticket #100)
     *
     * Nur dann darf die Oberflaeche den Anruf aus der Hand geben, also einen
     * `tel:`-Verweis anstelle des Portalanrufs anbieten. Bei einer Freigabe
     * auf Zeit (ATTEMPTS) ist der Lead offen: Ein Gespraech ausserhalb des
     * Portals erzeugt keinen Versuch, und der Lead wird gutgeschrieben,
     * obwohl der Kontakt zustande kam.
     */
    public function isFinal(): bool
    {
        return $this->released && $this->reason->isFinal();
    }

    /**
     * Ist die Freigabe durch die Gutschrift zurueckgenommen worden?
     *
     * Der Fall braucht einen eigenen Namen, weil er der einzige ist, in dem
     * ein Kaeufer die Nummer schon gesehen haben kann und sie trotzdem nicht
     * mehr bekommt.
     */
    public function isRevoked(): bool
    {
        return $this->reason === PhoneReleaseReason::REVOKED_CREDIT;
    }
}
