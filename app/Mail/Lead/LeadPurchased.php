<?php

declare(strict_types=1);

namespace App\Mail\Lead;

use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\User;
use App\Presenters\LeadPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Die Kaufbestaetigung an den Kaeufer -- mit den Kontaktdaten im Klartext
 * (FB-054).
 *
 * Klartext steht hier nicht, weil die Mail es darf, sondern weil der
 * LeadContactResolver es entscheidet: Der Kaufbeleg existiert, also liefert
 * contactFor() dem Kaeufer die unverdeckte Fassung. Die Mail baut die
 * Maskierung nicht nach und kennt die Kontaktspalten nicht -- sie gibt aus,
 * was der Presenter herausgibt.
 */
class LeadPurchased extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Lead $lead,
        public LeadPurchase $purchase,
        public User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('marketplace.purchase.mail.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lead.purchased',
            with: [
                'presenter' => new LeadPresenter($this->lead, $this->recipient),
            ],
        );
    }
}
