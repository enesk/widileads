<?php

declare(strict_types=1);

namespace App\Mail\Wallet;

use App\Models\Settlement;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Meldung an den Betreiber ueber einen technisch gescheiterten Einzug
 * (LP-POSTPAID-008).
 *
 * Empfaenger ist ausschliesslich die Support-Adresse. Der Kaeufer bekommt hier
 * bewusst nichts: Ein Netzwerk- oder API-Fehler auf unserer Seite ist kein
 * Zahlungsverzug, und eine Mahnung dafuer waere schlicht falsch. Die Forderung
 * bleibt bestehen, sie muss von Hand neu angestossen werden
 * (LP-POSTPAID-012).
 *
 * Die Mail traegt nur Kennung, Betrag und Fehlertext -- mehr braucht es nicht,
 * um den Vorgang im Admin zu finden; der volle Kontext steht im Protokoll.
 */
class SettlementChargeErrorMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Settlement $settlement,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('marketplace.wallet.settlement_notice.error.subject', [
                'settlement' => (string) $this->settlement->getKey(),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wallet.settlement-charge-error',
            with: [
                'settlementId' => (string) $this->settlement->getKey(),
                'walletId' => (string) $this->settlement->wallet_id,
                'amount' => Money::format((int) $this->settlement->amount_cents),
                'reason' => $this->reason,
            ],
        );
    }
}
