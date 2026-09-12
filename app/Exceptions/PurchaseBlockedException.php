<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Wallet;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Das Konto des Kaeufers ist gesperrt, solange sein offener Betrag aussteht
 * (LP-POSTPAID-004).
 *
 * Die Sperre entsteht bei einer Zahlungsstoerung (LP-POSTPAID-009) und ist
 * bewusst unabhaengig vom Zahlungsmodus und vom Guthaben: Auch ein
 * zurueckgestufter Kaeufer, der frisch auflaedt, soll erst weiterkaufen
 * duerfen, wenn seine Forderung ausgeglichen ist. Sonst waere die Sperre
 * mit einer Aufladung von 1 EUR zu umgehen, ohne dass die Plattform ihr Geld
 * saehe.
 *
 * Geprueft wird ausschliesslich im WalletService bei einer Reservierung, also
 * an genau der Stelle, an der ein Kauf Geld bindet -- Gutschriften, Einzuege
 * und Korrekturen bleiben moeglich, sonst liesse sich die Sperre nie wieder
 * aufheben.
 *
 * Aufgehoben wird sie automatisch, sobald der Saldo wieder bei null oder
 * darueber steht (Ereignis App\Events\Wallet\WalletUnblocked).
 */
class PurchaseBlockedException extends RuntimeException
{
    public static function forWallet(Wallet $wallet): self
    {
        return new self(__('marketplace.wallet.errors.purchase_blocked', [
            'open' => number_format($wallet->open_amount_cents / 100, 2, ',', '.').' '.config('wallet.currency'),
        ]), Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Der HTTP-Status, der zu diesem Ausgang gehoert: Die Anfrage war in
     * Ordnung, nur der Zustand des Kontos laesst sie nicht zu.
     */
    public function status(): int
    {
        return $this->getCode() > 0 ? (int) $this->getCode() : Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
