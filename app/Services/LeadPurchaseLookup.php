<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lead;
use App\Models\Tenant;

/**
 * Hat dieser Kaeufer-Mandant diesen Lead gekauft? (FB-032)
 *
 * Die Frage entscheidet, ob Kontaktdaten im Klartext ausgeliefert werden --
 * deshalb steht sie als eigene, austauschbare Zusage da und nicht als
 * Datenbankabfrage mitten in der Maskierlogik.
 *
 * Bis FB-054 den Kaufvorgang samt `lead_purchases` baut, gibt es keine Kaeufe:
 * die Vorgabeumsetzung antwortet immer mit nein. FB-054 tauscht nur die
 * Container-Bindung aus -- genauso, wie FB-031 den SubmissionReceiver
 * uebernommen hat.
 */
interface LeadPurchaseLookup
{
    public function hasPurchased(Tenant $tenant, Lead $lead): bool;
}
