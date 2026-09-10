<?php

namespace App\Mail\Order;

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
            subject: __('marketplace.credit.mail.subject', ['app' => config('app.wordmark')]),
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
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
