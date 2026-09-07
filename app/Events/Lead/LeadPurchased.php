<?php

declare(strict_types=1);

namespace App\Events\Lead;

use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ein Lead wurde gekauft (FB-054).
 *
 * Wird ausgeloest, nachdem Kaufbeleg, Abbuchung und Zustandswechsel
 * festgeschrieben sind -- also erst, wenn der Kauf wirklich gilt. Zuhoerer
 * duerfen sich darauf verlassen, dass der Kaeufer die Kontaktdaten ab jetzt im
 * Klartext sehen darf.
 */
class LeadPurchased
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Lead $lead,
        public Tenant $buyer,
        public LeadPurchase $purchase,
        public ?User $actor = null,
        public bool $automatic = false,
    ) {}
}
