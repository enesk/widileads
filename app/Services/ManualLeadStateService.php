<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\AuditAction;
use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Exceptions\IllegalLeadTransition;
use App\Exceptions\InvalidLeadStateJustification;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Zustandswechsel eines Leads von Hand (FB-036).
 *
 * Der Zwangsstatuswechsel ist der Notausgang, wenn der fachliche Ablauf einen
 * Lead nicht dorthin bringt, wo er hingehoert -- etwa weil ein Kaeufer
 * ausserhalb der Plattform berichtet, dass die Nummer nicht existiert. Er
 * umgeht deshalb nichts von dem, was die Zustandsmaschine ausmacht:
 *
 * - Erlaubt ist nur, was in LeadTransitions::TABLE steht. Geschrieben wird
 *   ueber LeadStateService::transition(), nicht daran vorbei.
 * - Eine Begruendung ist Pflicht und liegt als `justification` im
 *   lead_state_log; der Grund des Uebergangs ist `manual_override`.
 * - Jeder Vorgang landet zusaetzlich im Audit-Log (FB-005), weil hier ein
 *   Mensch eingegriffen hat und nicht der Ablauf.
 */
class ManualLeadStateService
{
    public function __construct(
        private readonly LeadStateService $leadStateService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Setzt den Zustand eines Leads von Hand.
     *
     * @param  string  $justification  Pflichtbegruendung, mindestens
     *                                 config('funnel.lead.manual_state_min_justification_length') Zeichen.
     *
     * @throws AuthorizationException wenn der Benutzer kein Operator-Admin ist.
     * @throws InvalidLeadStateJustification wenn die Begruendung zu kurz ist.
     * @throws IllegalLeadTransition wenn der Uebergang nicht erlaubt ist.
     */
    public function force(Lead $lead, LeadState $to, string $justification, User $actor): Lead
    {
        if (! Gate::forUser($actor)->allows('leads.force-state', $lead)) {
            throw new AuthorizationException(__('funnel.lead.errors.force_state_forbidden'));
        }

        $justification = trim($justification);

        if (mb_strlen($justification) < $this->minimumJustificationLength()) {
            throw InvalidLeadStateJustification::tooShort($this->minimumJustificationLength());
        }

        $from = $lead->lead_state;

        $this->leadStateService->transition(
            $lead,
            $to,
            LeadTransitionReason::MANUAL_OVERRIDE,
            $actor,
            ['justification' => $justification],
        );

        $this->auditLogger->log(
            AuditAction::LEAD_STATE_FORCED,
            subject: $lead,
            payload: [
                'from' => $from->value,
                'to' => $to->value,
                'justification' => $justification,
            ],
            tenant: $lead->tenant,
            user: $actor,
        );

        return $lead;
    }

    private function minimumJustificationLength(): int
    {
        return (int) config('funnel.lead.manual_state_min_justification_length');
    }
}
