<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Constants\LeadContactStatus;
use App\Constants\LeadResolutionReason;
use App\Constants\LeadState;
use App\Events\Lead\LeadResolved;
use App\Models\Lead;
use App\Services\LeadResolver;
use Illuminate\Support\Facades\Event;
use Tests\Feature\FeatureTest;

/**
 * Ticket #92: Der Fristablauf greift nur nach Leads, die noch etwas zu
 * entscheiden haben.
 *
 * Geprueft wird die Klammer auf `lead_state`: Eine anerkannte Reklamation
 * wechselt den Lead in einen Endzustand und gibt das Geld zurueck, laesst
 * `contact_status` aber auf `open` stehen. Wird so ein Lead nachts trotzdem
 * als `billable`/`deadline` abgeschlossen, zaehlt ihn jede Auswertung auf der
 * falschen Seite.
 */
class ResolveExpiredLeadsTest extends FeatureTest
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('lead_calls.enforce_from', now()->subMonth()->toDateString());
    }

    public function test_lead_in_einem_endzustand_wird_vom_fristablauf_nicht_angefasst(): void
    {
        Event::fake([LeadResolved::class]);

        $invalid = $this->expiredLead(LeadState::UNGUELTIG);
        $unreachable = $this->expiredLead(LeadState::UNERREICHBAR);
        $sold = $this->expiredLead(LeadState::VERKAUFT);
        // `erreicht` ist kein Reklamationsfall, sondern der Normalfall: Der
        // 01:00-Lauf setzt den Zustand, abgebucht wird erst hier.
        $settled = $this->expiredLead(LeadState::ERREICHT);

        $this->artisan('leads:resolve-expired')->assertSuccessful();

        foreach ([$invalid, $unreachable] as $lead) {
            $lead->refresh();
            $this->assertSame(LeadContactStatus::OPEN, $lead->contact_status);
            $this->assertNull($lead->resolved_by);
            $this->assertNull($lead->resolved_at);
        }

        foreach ([$sold, $settled] as $lead) {
            $lead->refresh();
            $this->assertSame(LeadContactStatus::BILLABLE, $lead->contact_status);
            $this->assertSame(LeadResolutionReason::DEADLINE, $lead->resolved_by);
        }

        Event::assertDispatchedTimes(LeadResolved::class, 2);
    }

    public function test_resolver_entscheidet_einen_reklamierten_lead_auch_direkt_nicht(): void
    {
        Event::fake([LeadResolved::class]);

        $lead = $this->expiredLead(LeadState::UNERREICHBAR);

        $this->assertFalse(
            app(LeadResolver::class)->resolve($lead, LeadResolutionReason::DEADLINE),
        );

        $lead->refresh();
        $this->assertSame(LeadContactStatus::OPEN, $lead->contact_status);

        Event::assertNotDispatched(LeadResolved::class);
    }

    private function expiredLead(LeadState $state): Lead
    {
        return Lead::factory()->inState($state)->create([
            'tenant_id' => $this->createTenant()->id,
            'contact_status' => LeadContactStatus::OPEN,
            'delivered_at' => now()->subDays(8),
            'deadline_at' => now()->subDay(),
        ]);
    }
}
