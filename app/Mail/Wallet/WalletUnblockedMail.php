<?php

declare(strict_types=1);

namespace App\Mail\Wallet;

use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Die Kaufsperre ist aufgehoben (LP-POSTPAID-009).
 *
 * Der Gegenpol zur Rueckstufungsmail und deshalb kurz: Der Kaeufer hat seinen
 * offenen Betrag ausgeglichen und kann wieder kaufen. Kein Nachtreten, kein
 * Hinweis auf das, was vorher war.
 *
 * Pay as you go kommt damit ausdruecklich NICHT zurueck -- die
 * Wiederfreischaltung bleibt eine Entscheidung von Hand (LP-POSTPAID-012).
 */
class WalletUnblockedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $buyer,
        public int $balanceCents,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('marketplace.wallet.unblocked.mail.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wallet.unblocked',
            with: [
                'balance' => Money::format($this->balanceCents),
                'marketplaceUrl' => route('portal.marketplace', ['tenant' => $this->buyer->uuid]),
            ],
        );
    }
}
