<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Exceptions\IllegalLeadTransition;
use App\Models\Lead;
use App\Models\Scopes\TenantScopes;
use Illuminate\Support\Facades\DB;

/**
 * Gibt abgelaufene Reservierungen zurueck (FB-054).
 *
 * Eine Reservierung ist eine Zusage auf Zeit: Wer einen Lead in Arbeit hat,
 * blockiert ihn fuer alle anderen. Bricht der Kauf ab, weil der Browser
 * geschlossen wird oder ein Prozess stirbt, bliebe der Lead sonst dauerhaft
 * unverkaeuflich -- ohne dass es jemandem auffiele.
 *
 * Die Frist steht in config('funnel.lead.reservation_ttl').
 */
class LeadReservationService
{
    public function __construct(private readonly LeadStateService $states) {}

    /**
     * Setzt alle abgelaufenen Reservierungen zurueck auf `verfuegbar`.
     *
     * @return int Zahl der zurueckgegebenen Leads
     */
    public function releaseExpired(): int
    {
        $released = 0;

        $expired = Lead::query()
            // Der Lauf gehoert keinem Mandanten -- er raeumt ueber alle hinweg auf.
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('lead_state', LeadState::RESERVIERT->value)
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<=', now())
            ->orderBy('id')
            ->get();

        foreach ($expired as $lead) {
            if ($this->release($lead)) {
                $released++;
            }
        }

        return $released;
    }

    /**
     * Gibt einen einzelnen Lead zurueck.
     *
     * Ein Wettlauf mit einem Kauf, der gerade durchlaeuft, ist moeglich und
     * unproblematisch: Die Zustandsmaschine sperrt den Lead und weist den
     * Wechsel ab, wenn er inzwischen `verkauft` ist. Dieser Lauf zaehlt ihn
     * dann schlicht nicht mit.
     */
    private function release(Lead $lead): bool
    {
        try {
            DB::transaction(function () use ($lead): void {
                $this->states->transition(
                    $lead,
                    LeadState::VERFUEGBAR,
                    LeadTransitionReason::RESERVATION_EXPIRED,
                );

                $lead->update(['reserved_by' => null, 'reserved_until' => null]);
            });

            return true;
        } catch (IllegalLeadTransition) {
            return false;
        }
    }
}
