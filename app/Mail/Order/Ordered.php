<?php

namespace App\Mail\Order;

use App\Models\OneTimeProduct;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Ordered extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Order $order,
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('marketplace.wallet.order_mail.subject', ['app' => config('app.wordmark')]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.order.ordered',
            with: [
                'marketplaceUrl' => $this->marketplaceUrl(),
                'topupProductId' => $this->topupProductId(),
            ],
        );
    }

    /**
     * Link auf den Marktplatz des Workspace, fuer den gekauft wurde.
     *
     * Die Mail laeuft in der Queue und damit ohne aktiven Mandanten -- ohne
     * `withoutGlobalScopes()` faende die Abfrage den Workspace nicht und der
     * Kaeufer bekaeme eine Bestaetigung ohne Weg zurueck in die Anwendung.
     */
    private function marketplaceUrl(): ?string
    {
        if ($this->order->tenant_id === null) {
            return null;
        }

        $tenant = Tenant::query()->withoutGlobalScopes()->find($this->order->tenant_id);

        if (! $tenant instanceof Tenant) {
            return null;
        }

        return route('filament.dashboard.pages.marketplace', ['tenant' => $tenant]);
    }

    /**
     * Kennung des Aufladeprodukts, sofern diese Bestellung eine Aufladung
     * enthaelt (LP-WALLET-019).
     *
     * Die Aufladung laeuft ueber ein Einmalkauf-Produkt zu 1,00 EUR, dessen
     * Menge im Warenkorb der Betrag in Euro ist. Diese Menge ist ein
     * Umsetzungsdetail des Checkouts und gehoert nicht in eine Kundenmail --
     * die Ansicht nennt bei dieser Position deshalb den Betrag statt der Menge.
     * Alle uebrigen Einmalkaeufe bleiben unveraendert bei der Mengenangabe.
     *
     * Verglichen wird gegen die Produktkennung und nicht gegen den Slug jeder
     * Position, damit die Ansicht ohne weitere Abfrage auskommt.
     */
    private function topupProductId(): ?int
    {
        $slug = (string) config('wallet.topup_product_slug');

        if ($slug === '') {
            return null;
        }

        $productId = OneTimeProduct::query()->where('slug', $slug)->value('id');

        return $productId === null ? null : (int) $productId;
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
