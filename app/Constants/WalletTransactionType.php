<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Art einer Buchung im Wallet-Ledger (LP-WALLET-002).
 *
 * Das Wallet ist ein Journal: Der Saldo ist die Summe seiner Buchungen, nicht
 * ein gepflegter Zaehler. Deshalb traegt `amount_cents` das Vorzeichen, und die
 * Buchungsart sagt, welches Vorzeichen zulaessig ist -- eine Gutschrift mit
 * negativem Betrag waere ein stiller Geldschoepfungsfehler.
 *
 * Zwei Salden je Wallet, jede Buchung wirkt auf genau einen davon
 * (LP-WALLET-004): `reserve` und `release` bewegen ausschliesslich
 * `reserved_cents` (geblockt, aber noch nicht abgebucht), alle uebrigen Arten
 * bewegen `balance_cents`. Ein Kauf, der abgerechnet wird, besteht deshalb aus
 * zwei Buchungen -- `release` loest die Reservierung, `capture` bucht ab.
 *
 * Der Wert eines Case steht so in der Datenbank und darf nach dem ersten
 * Einsatz nicht mehr geaendert werden.
 */
enum WalletTransactionType: string
{
    /** Aufladung des Kaeufer-Wallets ueber den Checkout. Immer positiv. */
    case TOPUP = 'topup';

    /** Kaufpreis beim Leadkauf blocken. Erhoeht `reserved_cents`. */
    case RESERVE = 'reserve';

    /** Abbuchung des reservierten Kaufpreises nach erwiesener Erreichbarkeit. */
    case CAPTURE = 'capture';

    /** Reservierung aufloesen. Verringert `reserved_cents`. */
    case RELEASE = 'release';

    /** Rueckzahlung an den Kaeufer nach anerkannter Reklamation. */
    case REFUND = 'refund';

    /** Einnahme des Verkaeufers aus einem abgerechneten Lead (Netto). */
    case EARNING = 'earning';

    /** Provisionsanteil der Plattform, gebucht auf das Plattform-Wallet. */
    case COMMISSION = 'commission';

    /** Auszahlung an den Verkaeufer. Verlaesst das Wallet. */
    case PAYOUT = 'payout';

    /** Manuelle Korrektur durch den Plattform-Admin. Vorzeichen frei. */
    case ADJUSTMENT = 'adjustment';

    /** Eroeffnungssaldo aus der Migration bestehender Credits (LP-WALLET-014). */
    case OPENING_BALANCE = 'opening_balance';

    /**
     * Erwartetes Vorzeichen der Buchung auf dem betroffenen Saldo:
     * 1 = erhoeht, -1 = verringert, 0 = beides zulaessig.
     *
     * `earning` und `commission` lassen beide Richtungen zu (LP-WALLET-006):
     * Wird ein abgerechneter Lead erstattet, wird derselbe Betrag mit
     * umgekehrtem Vorzeichen zurueckgebucht. Eine eigene Buchungsart dafuer
     * waere schlechter lesbar -- so steht die Rueckbuchung als negative Zeile
     * neben der urspruenglichen Einnahme und kompensiert sie sichtbar.
     */
    public function sign(): int
    {
        return match ($this) {
            self::TOPUP, self::RESERVE, self::REFUND, self::OPENING_BALANCE => 1,
            self::CAPTURE, self::RELEASE, self::PAYOUT => -1,
            self::EARNING, self::COMMISSION, self::ADJUSTMENT => 0,
        };
    }

    /**
     * Bewegt diese Buchungsart den reservierten statt den freien Saldo?
     */
    public function affectsReservedBalance(): bool
    {
        return $this === self::RESERVE || $this === self::RELEASE;
    }

    /**
     * Spalte des Wallets, die diese Buchungsart fortschreibt.
     */
    public function balanceColumn(): string
    {
        return $this->affectsReservedBalance() ? 'reserved_cents' : 'balance_cents';
    }

    /**
     * Ist dieser Betrag fuer diese Buchungsart zulaessig?
     *
     * Null ist nie zulaessig: eine Buchung ohne Wirkung gehoert nicht in ein
     * Journal, sie verschleiert nur die Historie.
     */
    public function allowsAmount(int $amountCents): bool
    {
        return match ($this->sign()) {
            1 => $amountCents > 0,
            -1 => $amountCents < 0,
            default => $amountCents !== 0,
        };
    }

    /**
     * Darf diese Buchung den Saldo ins Minus druecken, wenn der Aufrufer es
     * ausdruecklich verlangt?
     *
     * Die manuelle Korrektur darf es immer -- der Admin muss das Minus wollen.
     * Die Rueckbuchung einer Einnahme oder Provision darf es ebenfalls
     * (LP-WALLET-006): Der Verkaeufer kann seinen Erloes laengst ausgezahlt
     * bekommen haben, wenn eine Reklamation anerkannt wird. Die Erstattung
     * daran scheitern zu lassen, hiesse den Kaeufer fuer die Liquiditaet des
     * Verkaeufers haften zu lassen; das Minus ist die ehrlichere Darstellung
     * und wird dem Admin in der Wallet-Uebersicht gezeigt.
     */
    public function allowsNegativeBalance(int $amountCents): bool
    {
        return match ($this) {
            self::ADJUSTMENT => true,
            self::EARNING, self::COMMISSION => $amountCents < 0,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
