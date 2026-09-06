<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Models\Lead;
use App\Models\LeadStateLog;
use App\Services\LeadRetentionService;
use Tests\Feature\FeatureTest;

/**
 * FB-037: Aufbewahrungsfrist und Anonymisierung.
 *
 * Geprueft wird, was teuer waere, wenn es falsch ist: dass nie verkaufte Leads
 * nach Fristablauf ueber die Zustandsmaschine ablaufen, dass die
 * Anonymisierung Preis- und Zaehldaten stehen laesst, dass innerhalb der Frist
 * nichts passiert und dass ein zweiter Lauf nichts mehr veraendert.
 */
class LeadRetentionTest extends FeatureTest
{
    private function retention(): LeadRetentionService
    {
        return app(LeadRetentionService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function lead(LeadState $state, int $ageInDays, array $attributes = []): Lead
    {
        return Lead::factory()->inState($state)->create([
            'tenant_id' => $this->createTenant()->id,
            'created_at' => now()->subDays($ageInDays),
            'updated_at' => now()->subDays($ageInDays),
            ...$attributes,
        ]);
    }

    private function retentionDays(): int
    {
        return (int) config('funnel.lead.retention_days');
    }

    public function test_unsold_lead_past_retention_expires_through_the_state_machine(): void
    {
        $lead = $this->lead(LeadState::VERFUEGBAR, $this->retentionDays() + 1);

        $result = $this->retention()->apply();

        $this->assertSame(1, $result['expired']);
        $lead->refresh();
        $this->assertSame(LeadState::ABGELAUFEN, $lead->lead_state);
        $this->assertNotNull($lead->anonymized_at, 'Ein abgelaufener Lead wird im selben Lauf anonymisiert.');

        // Der Wechsel muss durch LeadStateService gegangen sein -- ein direktes
        // update() wuerde keinen Protokolleintrag hinterlassen.
        $entry = LeadStateLog::query()->where('lead_id', $lead->id)->sole();
        $this->assertSame(LeadState::VERFUEGBAR, $entry->from_state);
        $this->assertSame(LeadState::ABGELAUFEN, $entry->to_state);
        $this->assertSame(LeadTransitionReason::RETENTION_ELAPSED, $entry->reason);
        $this->assertNull($entry->actor_id, 'Der Lauf handelt ohne Benutzer.');
    }

    public function test_anonymisation_keeps_price_state_and_timestamps(): void
    {
        $createdAt = now()->subDays($this->retentionDays() + 5);

        $lead = $this->lead(LeadState::ERREICHT, $this->retentionDays() + 5, [
            'settled_price' => 17.50,
            'settled_at' => $createdAt->copy()->addDays(2),
        ]);

        $settledAt = $lead->settled_at;

        $result = $this->retention()->apply();

        $this->assertSame(1, $result['anonymized']);
        $lead->refresh();
        $this->assertNotNull($lead->anonymized_at);
        $this->assertSame('17.50', $lead->settled_price, 'Der festgeschriebene Preis bleibt.');
        $this->assertEquals($settledAt, $lead->settled_at);
        $this->assertSame(LeadState::ERREICHT, $lead->lead_state, 'Der Zustand bleibt unveraendert.');
        $this->assertEquals($createdAt->startOfSecond(), $lead->created_at->startOfSecond());
    }

    public function test_leads_within_the_retention_period_are_untouched(): void
    {
        $available = $this->lead(LeadState::VERFUEGBAR, $this->retentionDays() - 1);
        $settled = $this->lead(LeadState::ERREICHT, $this->retentionDays() - 1, [
            'settled_price' => 15.00,
            'settled_at' => now()->subDay(),
        ]);

        $result = $this->retention()->apply();

        $this->assertSame(['expired' => 0, 'anonymized' => 0], $result);

        $this->assertSame(LeadState::VERFUEGBAR, $available->refresh()->lead_state);
        $this->assertNull($available->anonymized_at);
        $this->assertNull($settled->refresh()->anonymized_at);
        $this->assertSame(0, LeadStateLog::query()->count());
    }

    public function test_a_second_run_leaves_already_processed_leads_alone(): void
    {
        $this->lead(LeadState::VERFUEGBAR, $this->retentionDays() + 1);

        $this->retention()->apply();

        $lead = Lead::query()->sole();
        $anonymizedAt = $lead->anonymized_at;

        $second = $this->retention()->apply();

        $this->assertSame(['expired' => 0, 'anonymized' => 0], $second);
        $lead->refresh();
        $this->assertEquals($anonymizedAt, $lead->anonymized_at, 'Der Zeitpunkt der Anonymisierung wird nicht neu gesetzt.');
        $this->assertSame(1, LeadStateLog::query()->where('lead_id', $lead->id)->count());
    }
}
