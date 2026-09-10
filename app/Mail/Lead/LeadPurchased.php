<?php

declare(strict_types=1);

namespace App\Mail\Lead;

use App\Filament\Dashboard\Pages\PurchasedLeadDetail;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Presenters\LeadPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Die Kaufbestaetigung an den Kaeufer (FB-054).
 *
 * Sie nennt Name, E-Mail und Region und verweist fuer alles Weitere ins
 * Portal. Die Rufnummer steht ausdruecklich NICHT darin (FB-085): Eine Mail
 * laesst sich weiterleiten, archivieren und ausdrucken -- sie ist der falsche
 * Ort fuer eine Angabe, die erst nach der Abrechnung freigegeben wird und bei
 * `unreachable` nie. Angerufen wird ueber die Schaltflaeche im Portal, die die
 * Nummer serverseitig waehlt.
 *
 * Welche Kontaktdaten im Klartext stehen, entscheidet auch hier nicht die Mail,
 * sondern der LeadContactResolver. Die Mail baut die Maskierung nicht nach und
 * kennt die Kontaktspalten nicht -- sie gibt aus, was der Presenter herausgibt.
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

    /**
     * Der Link auf den gekauften Lead im Portal.
     *
     * Ueber den Kaeufer-Workspace des Kaufbelegs und nicht ueber den gerade
     * aktiven Mandanten: Die Mail wird in der Queue gerendert, dort gibt es
     * keinen Panel-Kontext.
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

    public function content(): Content
    {
        return new Content(
            view: 'emails.lead.purchased',
            with: [
                'presenter' => new LeadPresenter($this->lead, $this->recipient),
                'leadUrl' => $this->leadUrl(),
            ],
        );
    }
}
