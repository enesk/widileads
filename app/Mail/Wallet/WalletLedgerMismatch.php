<?php

declare(strict_types=1);

namespace App\Mail\Wallet;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Meldung an den Betreiber, dass ein Wallet-Saldo vom Ledger abweicht
 * (LP-WALLET-015).
 *
 * Empfaenger ist ausschliesslich die eigene Support-Adresse
 * (`config('app.support_email')`) -- eine Saldenabweichung ist ein interner
 * Vorgang, ueber den ein Mensch entscheidet.
 *
 * Die Mail traegt bewusst nur Kennungen und Betraege: Wer sie liest, soll
 * wissen, welches Wallet zu pruefen ist, nicht die halbe Buchhaltung im
 * Postfach haben.
 */
class WalletLedgerMismatch extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{wallet_id: int, owner: string, balance_expected: int, balance_actual: int, reserved_expected: int, reserved_actual: int, repaired: bool}>  $mismatches
     * @param  array{expected: int, actual: int}|null  $reservationTotals  Abweichung der Reservierungssumme gegen die offenen Leadkaeufe
     */
    public function __construct(
        public array $mismatches,
        public ?array $reservationTotals = null,
        public int $walletsChecked = 0,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('marketplace.wallet.verify.mail.subject', [
                'count' => count($this->mismatches) + ($this->reservationTotals === null ? 0 : 1),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.wallet.ledger-mismatch');
    }
}
