<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Stand eines Postpaid-Einzugs (LP-POSTPAID-002).
 *
 * Ein Settlement fasst den offenen Betrag eines Kaeufers zu einer Forderung
 * zusammen und zieht sie ueber den Zahlungsanbieter ein. Weil eine Lastschrift
 * erst Tage spaeter zurueckgegeben werden kann, ist der Weg mehrstufig: der
 * Einzug ist angestossen (`processing`), gelingt (`paid`), scheitert
 * (`failed`), wartet auf den zweiten Versuch (`retry_pending`) oder wird von
 * der Bank zurueckgegeben (`returned`).
 *
 * Der Wert eines Case steht so in der Datenbank und darf nach dem ersten
 * Einsatz nicht mehr geaendert werden.
 */
enum SettlementStatus: string
{
    /** Forderung steht fest, der Einzug ist noch nicht angestossen. */
    case PENDING = 'pending';

    /** Einzug laeuft beim Zahlungsanbieter, Ergebnis kommt per Webhook. */
    case PROCESSING = 'processing';

    /** Erster Versuch gescheitert, der zweite Versuch steht aus. */
    case RETRY_PENDING = 'retry_pending';

    /** Endstand: Betrag ist eingegangen und dem Wallet gutgeschrieben. */
    case PAID = 'paid';

    /** Endstand: Einzug endgueltig gescheitert, es folgt die Rueckstufung. */
    case FAILED = 'failed';

    /** Endstand: bereits gutgeschriebene Zahlung wurde zurueckgegeben. */
    case RETURNED = 'returned';

    /**
     * Ist der Einzug entschieden? Danach bewegt das Settlement kein Geld mehr.
     */
    public function isResolved(): bool
    {
        return match ($this) {
            self::PAID, self::FAILED, self::RETURNED => true,
            default => false,
        };
    }

    /**
     * Wartet dieses Settlement noch auf einen (weiteren) Einzugsversuch?
     */
    public function isOpen(): bool
    {
        return $this === self::PENDING || $this === self::RETRY_PENDING;
    }

    /**
     * Ist die Zahlung angestossen und das Ergebnis noch offen?
     */
    public function isInFlight(): bool
    {
        return $this === self::PROCESSING;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
