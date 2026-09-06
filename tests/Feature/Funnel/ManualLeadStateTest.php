<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\AuditAction;
use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Exceptions\IllegalLeadTransition;
use App\Exceptions\InvalidLeadStateJustification;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\LeadStateLog;
use App\Models\User;
use App\Services\ManualLeadStateService;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Feature\FeatureTest;

/**
 * FB-036: Manuelle Statussetzung durch einen Operator-Admin.
 *
 * Der Zwangsstatuswechsel umgeht den fachlichen Ablauf -- geprueft wird
 * deshalb, dass er nichts von dem umgeht, was die Zustandsmaschine ausmacht:
 * erlaubte Uebergaenge, Pflichtbegruendung, Protokoll.
 */
class ManualLeadStateTest extends FeatureTest
{
    private function service(): ManualLeadStateService
    {
        return app(ManualLeadStateService::class);
    }

    private function leadInState(LeadState $state): Lead
    {
        return Lead::factory()->inState($state)->create([
            'tenant_id' => $this->createTenant()->id,
        ]);
    }

    private function operatorAdmin(): User
    {
        return $this->createAdminUser();
    }

    public function test_a_forbidden_transition_is_rejected(): void
    {
        $lead = $this->leadInState(LeadState::NEU);

        $this->expectException(IllegalLeadTransition::class);

        try {
            $this->service()->force(
                $lead,
                LeadState::VERKAUFT,
                'Kaeufer hat den Lead telefonisch bestaetigt.',
                $this->operatorAdmin(),
            );
        } finally {
            $this->assertSame(LeadState::NEU, $lead->fresh()->lead_state);
            $this->assertSame(0, LeadStateLog::query()->where('lead_id', $lead->id)->count());
            $this->assertSame(0, AuditLog::query()->where('action', AuditAction::LEAD_STATE_FORCED)->count());
        }
    }

    public function test_a_justification_below_the_minimum_length_is_rejected(): void
    {
        $lead = $this->leadInState(LeadState::NEU);

        $this->expectException(InvalidLeadStateJustification::class);

        try {
            // Neun Zeichen -- und auch Leerzeichen drumherum zaehlen nicht mit.
            $this->service()->force($lead, LeadState::UNGUELTIG, '   Testfall   ', $this->operatorAdmin());
        } finally {
            $this->assertSame(LeadState::NEU, $lead->fresh()->lead_state);
            $this->assertSame(0, LeadStateLog::query()->where('lead_id', $lead->id)->count());
            $this->assertSame(0, AuditLog::query()->where('action', AuditAction::LEAD_STATE_FORCED)->count());
        }
    }

    public function test_an_allowed_transition_is_written_with_its_justification(): void
    {
        $lead = $this->leadInState(LeadState::VERFUEGBAR);
        $admin = $this->operatorAdmin();
        $justification = 'Telefonnummer gehoert laut Rueckmeldung des Kaeufers zu einer Praxis.';

        $this->service()->force($lead, LeadState::UNGUELTIG, $justification, $admin);

        $this->assertSame(LeadState::UNGUELTIG, $lead->fresh()->lead_state);

        $entry = LeadStateLog::query()->where('lead_id', $lead->id)->sole();
        $this->assertSame(LeadState::VERFUEGBAR, $entry->from_state);
        $this->assertSame(LeadState::UNGUELTIG, $entry->to_state);
        $this->assertSame(LeadTransitionReason::MANUAL_OVERRIDE, $entry->reason);
        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame(['justification' => $justification], $entry->meta);

        $audit = AuditLog::query()->where('action', AuditAction::LEAD_STATE_FORCED)->sole();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame($lead->tenant_id, $audit->tenant_id);
        $this->assertSame((string) $lead->id, $audit->subject_id);
        // assertEquals statt assertSame: MySQL gibt JSON-Schluessel in eigener
        // Reihenfolge zurueck, die Reihenfolge ist fachlich ohne Bedeutung.
        $this->assertEquals([
            'from' => 'verfuegbar',
            'to' => 'ungueltig',
            'justification' => $justification,
        ], $audit->payload);
    }

    public function test_a_user_without_operator_admin_rights_may_not_set_the_state(): void
    {
        $lead = $this->leadInState(LeadState::VERFUEGBAR);

        $this->expectException(AuthorizationException::class);

        try {
            $this->service()->force(
                $lead,
                LeadState::UNGUELTIG,
                'Sieht nach einer Fehleingabe aus.',
                $this->createUser(),
            );
        } finally {
            $this->assertSame(LeadState::VERFUEGBAR, $lead->fresh()->lead_state);
            $this->assertSame(0, LeadStateLog::query()->where('lead_id', $lead->id)->count());
        }
    }
}
