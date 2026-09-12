<?php

declare(strict_types=1);

namespace App\Mail\Wallet;

use App\Models\Settlement;
use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Bestaetigung eines eingegangenen Postpaid-Einzugs (LP-POSTPAID-008).
 *
 * Sie nennt Betrag, Zahlungsmittel und -- sobald der Beleg fuer Abrechnungen
 * gebaut ist (LP-POSTPAID-015) -- den Verweis darauf. Gibt es noch keinen
 * Beleg, bleibt der Verweis weg, statt auf eine leere Seite zu fuehren.
 *
 * Verschickt wird sie ausschliesslich vom SettlementService, nach der Buchung
 * im Ledger. Die Reihenfolge ist Absicht: Eine Bestaetigung, deren Buchung
 * scheitert, waere schlimmer als eine Buchung ohne Bestaetigung.
 */
class SettlementPaidMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Settlement $settlement,
        public Tenant $buyer,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('marketplace.wallet.settlement_notice.paid.subject', [
                'amount' => Money::format((int) $this->settlement->amount_cents),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wallet.settlement-paid',
            with: [
                'amount' => Money::format((int) $this->settlement->amount_cents),
                'paidOn' => ($this->settlement->paid_at ?? now())->translatedFormat('d.m.Y'),
                'method' => $this->settlement->paymentMethod?->label()
                    ?? __('marketplace.wallet.settlement_notice.method_unknown'),
                'invoiceReference' => $this->settlement->invoice_reference,
                'invoiceUrl' => $this->invoiceUrl(),
            ],
        );
    }

    /**
     * Verweis auf den Beleg (LP-POSTPAID-015). Ohne Belegnummer bleibt der
     * Knopf weg, statt auf eine leere Seite zu fuehren -- der Beleg entsteht
     * im SettlementObserver und kann im Ausnahmefall nachhinken.
     */
    private function invoiceUrl(): ?string
    {
        if ($this->settlement->invoice_reference === null) {
            return null;
        }

        return route('buyer.settlements.invoice', ['settlement' => $this->settlement->getKey()]);
    }
}
