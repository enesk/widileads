<?php

declare(strict_types=1);

namespace App\Mail\Lead;

use App\Models\LeadComplaint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Meldung an den Support, dass ein Kaeufer einen Lead reklamiert hat.
 *
 * Empfaenger ist ausschliesslich die eigene Support-Adresse
 * (`config('app.support_email')`) -- die Reklamation ist ein interner Vorgang,
 * ueber den ein Mensch entscheidet.
 *
 * Kontaktdaten des Leads stehen bewusst NICHT in dieser Mail. Fuer die
 * Entscheidung braucht es sie nicht, und jede Mail, die sie mittraegt, ist eine
 * Kopie mehr ausserhalb der Anwendung.
 */
class LeadComplaintFiled extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public LeadComplaint $complaint) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('marketplace.complaint.mail.subject', [
                'lead' => $this->complaint->lead_id,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lead.complaint-filed');
    }
}
