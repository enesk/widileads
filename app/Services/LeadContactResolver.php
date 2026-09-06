<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\LeadContact;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;

/**
 * Die einzige Stelle, die entscheidet, wer Kontaktdaten im Klartext sieht
 * (FB-032, Architekturleitsatz 5).
 *
 * Klartext bekommt:
 *
 * - der Plattform-Admin,
 * - wer zum Mandanten gehoert, dem der Lead gehoert (der Betreiber hat die
 *   Daten selbst erhoben),
 * - ein Kaeufer-Mandant, der diesen Lead gekauft hat.
 *
 * Alle anderen -- auch ein angemeldeter Kaeufer vor dem Kauf und jeder nicht
 * angemeldete Aufruf -- bekommen die verdeckte Fassung. Die Vorgabe ist
 * bewusst "verdeckt": Wer diesen Dienst falsch benutzt, verraet nichts.
 *
 * Die Kaufabfrage laeuft ueber LeadPurchaseLookup und nicht direkt gegen die
 * Datenbank. Bis FB-054 gibt es keine Kaeufe; danach reicht dort ein Tausch der
 * Bindung.
 */
class LeadContactResolver
{
    public function __construct(private readonly LeadPurchaseLookup $purchases) {}

    public function for(Lead $lead, ?User $viewer): LeadContact
    {
        $contact = LeadContact::fromLead($lead);

        return $this->maySeeClearText($lead, $viewer) ? $contact : $contact->masked();
    }

    public function maySeeClearText(Lead $lead, ?User $viewer): bool
    {
        if (! $viewer instanceof User) {
            return false;
        }

        if ((bool) $viewer->is_admin) {
            return true;
        }

        foreach ($viewer->tenants as $tenant) {
            if (! $tenant instanceof Tenant) {
                continue;
            }

            if ((int) $tenant->getKey() === (int) $lead->tenant_id) {
                return true;
            }

            if ($this->purchases->hasPurchased($tenant, $lead)) {
                return true;
            }
        }

        return false;
    }
}
