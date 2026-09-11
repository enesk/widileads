<?php

declare(strict_types=1);

namespace App\Mail\Wallet;

use App\Models\PayoutRequest;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Die Entscheidung ueber eine Auszahlung, an den Verkaeufer (LP-WALLET-010).
 *
 * Eine Mail fuer beide Ausgaenge statt zweier fast gleicher Klassen: Der
 * Unterschied zwischen ueberwiesen und abgelehnt sind die Texte und die
 * Begruendung, nicht der Aufbau. Welcher Fall gilt, sagt
 * `payout.status` -- die Ansicht liest ihn und nimmt die passenden
 * Sprachschluessel.
 *
 * Der Betrag kommt aus der Anforderung und nicht aus dem Wallet: Bis die Mail
 * in der Queue gerendert wird, kann ein weiterer Lead den Saldo bewegt haben.
 */
class PayoutProcessedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public PayoutRequest $payout,
        public Tenant $seller,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('marketplace.wallet.payout.mail.processed.'.$this->payout->status->value.'.subject', [
                'amount' => $this->formatted((int) $this->payout->amount_cents),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wallet.payout-processed',
            with: [
                'status' => $this->payout->status->value,
                'amount' => $this->formatted((int) $this->payout->amount_cents),
                'iban' => $this->payout->maskedIban(),
                'note' => $this->payout->note,
            ],
        );
    }

    private function formatted(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, ',', '.').' €';
    }
}
