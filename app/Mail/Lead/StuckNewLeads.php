<?php

declare(strict_types=1);

namespace App\Mail\Lead;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Meldung an den Betreiber, dass Leads im Zustand `neu` liegengeblieben sind
 * (Ticket #108).
 *
 * Empfaenger ist ausschliesslich die eigene Support-Adresse
 * (`config('app.support_email')`) -- ein ausgefallener Pruefjob ist ein
 * interner Vorfall, ueber den ein Mensch entscheidet.
 *
 * Die Mail traegt bewusst nur Kennungen, Zeitpunkte und Alter: Wer sie liest,
 * soll wissen, welche Leads zu pruefen sind, und nicht die Kontaktdaten des
 * Endkunden im Postfach haben.
 */
class StuckNewLeads extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{id: int, tenant_id: int|null, funnel_id: int|null, created_at: string, age_hours: int}>  $leads  Belegzeilen, gekuerzt auf die Grenze des Laufs
     * @param  int  $hours  Verweildauer, ab der ein Lead als liegengeblieben gilt
     * @param  int  $total  Gesamtzahl der Treffer -- kann groesser sein als die Belegzeilen
     */
    public function __construct(
        public array $leads,
        public int $hours,
        public int $total,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('funnel.lead.stuck_new.mail.subject', ['count' => $this->total]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lead.stuck-new');
    }
}
