<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Stand der Geldseite eines Leadkaufs (LP-WALLET-003).
 *
 * Getrennt vom Zustand des Leads (LeadState) und von seiner Erreichbarkeit
 * (LeadContactStatus): Diese Achse sagt nur, wo das Geld des Kaeufers gerade
 * steht. Gesetzt wird der Wert ausschliesslich vom PurchaseService
 * (LP-WALLET-006), jeder Wechsel hat eine Ledger-Buchung als Beleg.
 *
 * Der Wert eines Case steht so in der Datenbank und darf nach dem ersten
 * Einsatz nicht mehr geaendert werden.
 */
enum PurchaseStatus: string
{
    /** Kaufpreis ist geblockt, aber noch nicht abgebucht. */
    case RESERVED = 'reserved';

    /** Endstand: abgebucht, Verkaeufer und Plattform sind gutgeschrieben. */
    case CAPTURED = 'captured';

    /** Endstand: Reservierung aufgeloest, der Kaeufer zahlt nicht. */
    case RELEASED = 'released';

    /** Endstand: bereits abgebuchter Betrag wurde zurueckgezahlt. */
    case REFUNDED = 'refunded';

    /**
     * Ist die Geldseite entschieden? Danach bewegt der Kauf kein Guthaben mehr.
     */
    public function isResolved(): bool
    {
        return $this !== self::RESERVED;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
