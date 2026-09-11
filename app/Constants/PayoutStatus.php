<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Stand einer Auszahlungsanforderung (LP-WALLET-010).
 *
 * Die Achse sagt nur, wo die Ueberweisung steht -- das Geld hat das Wallet
 * bereits mit der Anforderung verlassen (App\Services\Wallet\PayoutService).
 * Ohne diese sofortige Abbuchung koennte derselbe Betrag zweimal angefordert
 * werden, und der Verkaeufer bekaeme ihn zweimal ausgezahlt.
 *
 * Gesetzt wird der Wert ausschliesslich vom PayoutService, jeder Wechsel hat
 * eine Ledger-Buchung oder eine Entscheidung eines Admins als Beleg.
 *
 * Der Wert eines Case steht so in der Datenbank und darf nach dem ersten
 * Einsatz nicht mehr geaendert werden.
 */
enum PayoutStatus: string
{
    /** Angefordert und abgebucht, aber noch nicht ueberwiesen. */
    case REQUESTED = 'requested';

    /** Endstand: Enes hat ueberwiesen. */
    case PAID = 'paid';

    /** Endstand: abgelehnt, der Betrag ist zurueckgebucht. */
    case REJECTED = 'rejected';

    /**
     * Wartet diese Anforderung noch auf eine Entscheidung?
     */
    public function isOpen(): bool
    {
        return $this === self::REQUESTED;
    }

    /**
     * Ist entschieden? Danach bewegt die Anforderung kein Guthaben mehr.
     */
    public function isResolved(): bool
    {
        return $this !== self::REQUESTED;
    }

    public function label(): string
    {
        return __('marketplace.wallet.payout.status.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
