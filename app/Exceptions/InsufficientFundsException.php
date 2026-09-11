<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Wallet;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Das Guthaben eines Wallets reicht fuer diese Buchung nicht (LP-WALLET-005).
 *
 * Kein Wallet darf ins Minus laufen -- weder das des Kaeufers noch das der
 * Plattform. Ein negativer Saldo waere eine stillschweigende Kreditvergabe:
 * Geld, das ausgegeben wurde, ohne dass es je eingezahlt wurde. Die einzige
 * Ausnahme ist die manuelle Korrektur des Admins, und die muss ausdruecklich
 * mit `allowNegative` verlangt werden.
 *
 * Geprueft wird ausschliesslich im WalletService, innerhalb der
 * Buchungstransaktion und unter Sperre der Wallet-Zeile -- zwei gleichzeitige
 * Reservierungen auf knappe Deckung koennen so nicht gemeinsam durchgehen.
 *
 * Die Meldungen sind deutsch und fuer die Oberflaeche gedacht: sie landen im
 * Marktplatz direkt vor dem Kaeufer.
 */
class InsufficientFundsException extends RuntimeException
{
    /**
     * Eine Reservierung uebersteigt das frei verfuegbare Guthaben. Massgeblich
     * ist `available_cents`, nicht der Saldo: was fuer andere Leads schon
     * geblockt ist, steht nicht mehr zur Verfuegung.
     */
    public static function forReservation(Wallet $wallet, int $amountCents): self
    {
        return new self(__('marketplace.wallet.errors.insufficient_reserve', [
            'available' => self::formatAmount($wallet->available_cents),
            'required' => self::formatAmount($amountCents),
            'missing' => self::formatAmount(max(0, $amountCents - $wallet->available_cents)),
        ]), Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Eine Buchung wuerde den Saldo unter null druecken.
     */
    public static function forBalance(Wallet $wallet, int $amountCents): self
    {
        return new self(__('marketplace.wallet.errors.insufficient_balance', [
            'balance' => self::formatAmount($wallet->balance_cents),
            'required' => self::formatAmount(abs($amountCents)),
        ]));
    }

    /**
     * Eine Aufloesung uebersteigt den reservierten Betrag. Anders als die
     * beiden anderen Faelle ist das kein Geldproblem des Nutzers, sondern ein
     * Fehler im Aufrufer -- eine Reservierung wurde doppelt aufgeloest.
     */
    public static function forRelease(Wallet $wallet, int $amountCents): self
    {
        return new self(__('marketplace.wallet.errors.release_exceeds_reserved', [
            'reserved' => self::formatAmount($wallet->reserved_cents),
            'requested' => self::formatAmount(abs($amountCents)),
        ]));
    }

    /**
     * Cent als Betrag in der Schreibweise, die der Kaeufer im Portal sieht.
     */
    /**
     * Der HTTP-Status, der zu diesem Ausgang gehoert: Die Anfrage war in
     * Ordnung, nur das Guthaben reicht nicht (LP-WALLET-007).
     */
    public function status(): int
    {
        return $this->getCode() > 0 ? (int) $this->getCode() : Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    private static function formatAmount(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' '.config('wallet.currency');
    }
}
