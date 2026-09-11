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
 * Meldung an den Betreiber, dass ein Verkaeufer eine Auszahlung angefordert hat
 * (LP-WALLET-010).
 *
 * Empfaenger ist ausschliesslich die eigene Support-Adresse
 * (`config('app.support_email')`): Ueberwiesen wird von Hand, und ohne diese
 * Meldung bliebe die Anforderung liegen, bis jemand von sich aus in die Liste
 * schaut.
 *
 * Die Mail nennt bewusst nur die letzte Vierergruppe der IBAN. Die
 * vollstaendige Nummer steht verschluesselt am Mandanten und gehoert nicht in
 * ein Postfach; wer ueberweist, holt sie sich in der Auszahlungsliste.
 */
class PayoutRequestedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public PayoutRequest $payout,
        public Tenant $seller,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('marketplace.wallet.payout.mail.requested.subject', [
                'seller' => (string) $this->seller->name,
                'amount' => $this->formatted((int) $this->payout->amount_cents),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wallet.payout-requested',
            with: [
                'amount' => $this->formatted((int) $this->payout->amount_cents),
                'iban' => $this->payout->maskedIban(),
                'sellerName' => (string) $this->seller->name,
            ],
        );
    }

    private function formatted(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, ',', '.').' €';
    }
}
