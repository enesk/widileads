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

        if ($this->isOwnLead($lead, $viewer)) {
            return $contact;
        }

        $buyer = $this->purchasingTenantOf($lead, $viewer);

        if ($buyer instanceof Tenant) {
            return $this->withGatedPhone($lead, $contact, $buyer);
        }

        return $contact->masked();
    }

    /**
     * Kontaktdaten aus Sicht eines Workspaces (FB-030d).
     *
     * Ueber die API fragt kein Benutzer, sondern der Workspace selbst: Das
     * Token gehoert dem Tenant, nicht einer Person (FB-006). Die Regel ist
     * dieselbe wie fuer Benutzer, nur ohne den Umweg ueber deren
     * Mitgliedschaften -- der Eigentuemer sieht Klartext, ein Kaeufer erst nach
     * dem Kauf.
     */
    public function forTenant(Lead $lead, ?Tenant $tenant): LeadContact
    {
        $contact = LeadContact::fromLead($lead);

        if (! $tenant instanceof Tenant) {
            return $contact->masked();
        }

        if ((int) $tenant->getKey() === (int) $lead->tenant_id) {
            return $contact;
        }

        if ($this->purchases->hasPurchased($tenant, $lead)) {
            return $this->withGatedPhone($lead, $contact, $tenant);
        }

        return $contact->masked();
    }

    /**
     * Die Rufnummernsperre des Kaeufers (FB-085).
     *
     * Ein Kauf allein gibt die Rufnummer nicht frei. Angerufen wird ueber die
     * Bridge, die die Nummer serverseitig waehlt; der Kaeufer selbst bekommt
     * sie erst, wenn die Erreichbarkeitspruefung mit `billable` geendet hat --
     * also wenn der Lead abgerechnet wird. Bei `unreachable` bleibt sie
     * dauerhaft verdeckt: Dafuer gibt es eine Gutschrift und keine Nummer.
     *
     * Fuer den Betreiber gilt das nicht: Ihm gehoeren die Daten, er hat sie
     * selbst erhoben.
     */
    private function withGatedPhone(Lead $lead, LeadContact $contact, Tenant $buyer): LeadContact
    {
        $revealed = $lead->revealedPhone($buyer);

        if ($revealed !== null) {
            return $contact->withPhone($revealed, phoneMasked: false);
        }

        return $contact->withPhone($lead->maskedPhone(), phoneMasked: true);
    }

    /**
     * Gehoert der Lead dem Betrachter selbst -- als Plattform-Admin oder als
     * Mitglied des Eigentuemer-Workspaces?
     */
    private function isOwnLead(Lead $lead, ?User $viewer): bool
    {
        if (! $viewer instanceof User) {
            return false;
        }

        if ((bool) $viewer->is_admin) {
            return true;
        }

        foreach ($viewer->tenants as $tenant) {
            if ($tenant instanceof Tenant && (int) $tenant->getKey() === (int) $lead->tenant_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Der Kaeufer-Workspace des Betrachters, der diesen Lead gekauft hat.
     *
     * Ein Benutzer kann in mehreren Workspaces sein; massgeblich ist der, ueber
     * den der Kauf laeuft, denn an ihm haengt auch die Freigabe der Rufnummer.
     */
    private function purchasingTenantOf(Lead $lead, ?User $viewer): ?Tenant
    {
        if (! $viewer instanceof User) {
            return null;
        }

        foreach ($viewer->tenants as $tenant) {
            if ($tenant instanceof Tenant && $this->purchases->hasPurchased($tenant, $lead)) {
                return $tenant;
            }
        }

        return null;
    }

    public function tenantMaySeeClearText(Lead $lead, ?Tenant $tenant): bool
    {
        if (! $tenant instanceof Tenant) {
            return false;
        }

        if ((int) $tenant->getKey() === (int) $lead->tenant_id) {
            return true;
        }

        return $this->purchases->hasPurchased($tenant, $lead);
    }

    /**
     * Die unmaskierte Fassung fuer die serverseitige Auswertung (FB-033).
     *
     * Ausdruecklich NICHT fuer die Ausgabe: Was hier herauskommt, darf niemals
     * an einen Betrachter gelangen. Gedacht ist die Methode fuer Pruefungen,
     * die auf echten Werten arbeiten muessen -- ob eine Telefonnummer lesbar
     * ist, ob eine Adresse zu einem Wegwerf-Anbieter gehoert.
     *
     * Es gibt sie, damit auch diese Zugriffe durch LeadContact laufen statt an
     * den Rohspalten. Wer Kontaktdaten anzeigt, nimmt for().
     */
    public function internal(Lead $lead): LeadContact
    {
        return LeadContact::fromLead($lead);
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
