<?php

declare(strict_types=1);

namespace App\Mail\Lead;

use App\Filament\Dashboard\Pages\PurchasedLeadDetail;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Die Erinnerung an einen Kaeufer, dass die Frist zur Erreichbarkeitspruefung
 * in weniger als 24 Stunden ablaeuft (FB-084, Ticket #14).
 *
 * Die Mail traegt bewusst keine Kontaktdaten und keine Lead-Nummer: Sie ist
 * eine Fristmeldung, kein Datenauszug. Zur Wiedererkennung genuegt die
 * Referenz -- die ersten Stellen der Lead-UUID -- und der Link ins Portal, wo
 * der Kaeufer nach Anmeldung ohnehin alles sieht.
 */
class LeadDeadlineReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  int  $validFailedAttempts  Gueltige erfolglose Versuche des Leads ueber alle Kaeufer
     *                                    hinweg -- dieselbe Menge, aus der das Regelwerk (FB-083)
     *                                    entscheidet.
     */
    public function __construct(
        public Lead $lead,
        public LeadPurchase $purchase,
        public int $validFailedAttempts,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('call.reminder.mail.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lead.deadline-reminder',
            with: [
                'reference' => $this->reference(),
                'requiredAttempts' => (int) config('lead_calls.unreachable_attempts'),
                'deadlineAt' => $this->lead->deadline_at?->copy()->setTimezone('Europe/Berlin'),
                'leadUrl' => $this->leadUrl(),
            ],
        );
    }

    /**
     * Die Referenz, unter der Kaeufer und Support ueber den Lead sprechen,
     * ohne seine laufende Nummer preiszugeben.
     */
    private function reference(): string
    {
        return strtoupper(substr((string) $this->lead->uuid, 0, 8));
    }

    /**
     * Der Link auf den gekauften Lead im Portal.
     *
     * Ueber den Kaeufer-Workspace des Kaufbelegs: Gerendert wird in der Queue,
     * dort gibt es keinen Panel-Kontext (wie in LeadPurchased).
     */
    private function leadUrl(): ?string
    {
        $buyer = $this->purchase->buyer;

        if (! $buyer instanceof Tenant) {
            return null;
        }

        return PurchasedLeadDetail::getUrl(
            ['purchase' => $this->purchase->getKey()],
            panel: 'dashboard',
            tenant: $buyer,
        );
    }
}
