<?php

declare(strict_types=1);

namespace App\Mail\Wallet;

use App\Constants\LeadContactStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Meldung an den Betreiber, dass ein entschiedener Lead nicht abgerechnet
 * werden konnte (LP-WALLET-008).
 *
 * Empfaenger ist ausschliesslich die eigene Support-Adresse: Ein haengender
 * Kaufbeleg ist ein interner Vorgang, ueber den ein Mensch entscheidet -- der
 * Kaeufer soll keine Fehlermeldung bekommen, sondern eine aufgeloeste
 * Reservierung.
 *
 * Die Mail traegt nur Kennung, Endstand und Fehlertext. Mehr braucht es nicht,
 * um den Kauf im Admin zu finden; der volle Kontext steht im Protokoll.
 */
class LeadSettlementFailed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $leadId,
        public LeadContactStatus $contactStatus,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('marketplace.wallet.settlement.mail.subject', ['lead' => $this->leadId]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wallet.settlement-failed',
            with: [
                'contactStatusLabel' => $this->contactStatus->label(),
            ],
        );
    }
}
