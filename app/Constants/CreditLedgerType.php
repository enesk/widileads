<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Art einer Buchung im Guthabenkonto (FB-052).
 *
 * Das Guthabenkonto ist ein Journal: Der Saldo ist die Summe seiner Buchungen,
 * nicht ein gepflegter Zaehler. Deshalb traegt `credits` das Vorzeichen, und die
 * Buchungsart sagt, welches Vorzeichen zulaessig ist -- eine Abbuchung mit
 * positivem Betrag waere ein stiller Geldschoepfungsfehler.
 *
 * Der Wert eines Case steht so in der Datenbank und darf nach dem ersten
 * Einsatz nicht mehr geaendert werden.
 */
enum CreditLedgerType: string
{
    /** Guthabenkauf ueber ein Einmalkauf-Produkt (Stripe). Immer positiv. */
    case PURCHASE = 'purchase';

    /** Abbuchung beim Kauf eines Leads (FB-054). Immer negativ. */
    case DEBIT = 'debit';

    /** Gutschrift nach einer anerkannten Reklamation (FB-058). Immer positiv. */
    case REFUND = 'refund';

    /**
     * Manuelle Korrektur durch den Plattform-Admin -- auch der Weg fuer
     * Guthaben auf Rechnung (Entscheidung 1 vom 2026-09-06). Vorzeichen frei,
     * weil sowohl Gutschrift als auch Korrektur nach unten vorkommen.
     */
    case ADJUSTMENT = 'adjustment';

    /**
     * Ist dieser Betrag fuer diese Buchungsart zulaessig?
     *
     * Null ist nie zulaessig: eine Buchung ohne Wirkung gehoert nicht in ein
     * Journal, sie verschleiert nur die Historie.
     */
    public function allowsCredits(int $credits): bool
    {
        return match ($this) {
            self::PURCHASE, self::REFUND => $credits > 0,
            self::DEBIT => $credits < 0,
            self::ADJUSTMENT => $credits !== 0,
        };
    }

    /**
     * Verringert diese Buchungsart das Guthaben? Nur solche Buchungen brauchen
     * die Deckungspruefung.
     */
    public function reducesBalance(int $credits): bool
    {
        return $credits < 0;
    }

    public function label(): string
    {
        return __('marketplace.credit.type.'.$this->value);
    }

    /**
     * @return array<string, string> Wert => Beschriftung, fuer Auswahl- und Filterfelder.
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
