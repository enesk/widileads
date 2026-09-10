<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\LeadContactStatus;
use App\Constants\LeadState;
use App\Constants\TenantType;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Services\LeadReachabilityService;
use Illuminate\Support\Carbon;
use Tests\Feature\FeatureTest;

/**
 * FB-085: Die Erreichbarkeitsquote je Kaeufer.
 *
 * Geprueft wird die Rechnung selbst -- sie ist die Grundlage, auf der ein
 * Kaeufer im Streitfall als auffaellig gilt, und sie entsteht in einer
 * Abfrage mit bedingten Summen. Ein Fehler darin faellt sonst niemandem auf.
 */
class LeadReachabilityTest extends FeatureTest
{
    public function test_metrics_match_the_hand_calculation(): void
    {
        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $loud = Tenant::factory()->create(['type' => TenantType::BUYER, 'name' => 'Lauter Kaeufer']);
        $quiet = Tenant::factory()->create(['type' => TenantType::BUYER, 'name' => 'Stiller Kaeufer']);

        // Lauter Kaeufer: 1 erreicht, 3 nicht erreichbar, 1 offen.
        $this->purchases($operator, $loud, LeadContactStatus::BILLABLE, 1);
        $this->purchases($operator, $loud, LeadContactStatus::UNREACHABLE, 3);
        $this->purchases($operator, $loud, LeadContactStatus::OPEN, 1);

        // Stiller Kaeufer: 7 erreicht, 1 nicht erreichbar.
        $this->purchases($operator, $quiet, LeadContactStatus::BILLABLE, 7);
        $this->purchases($operator, $quiet, LeadContactStatus::UNREACHABLE, 1);

        $summary = app(LeadReachabilityService::class)->summary();

        // Handrechnung Durchschnitt: 4 von 12 entschiedenen Leads sind nicht
        // erreichbar -> 33,33 Prozent.
        $this->assertEqualsWithDelta(4 / 12, $summary['average_rate'], 0.0001);
        $this->assertSame(13, $summary['leads_total']);
        $this->assertSame(8, $summary['billable']);
        $this->assertSame(4, $summary['unreachable']);

        $rows = collect($summary['rows'])->keyBy('buyer_tenant_id');

        $loudRow = $rows->get($loud->getKey());
        // 5 Leads gesamt, 4 entschieden, davon 3 nicht erreichbar -> 75 Prozent.
        $this->assertSame(5, $loudRow['leads_total']);
        $this->assertSame(1, $loudRow['billable']);
        $this->assertSame(3, $loudRow['unreachable']);
        $this->assertSame(1, $loudRow['open']);
        $this->assertEqualsWithDelta(0.75, $loudRow['unreachable_rate'], 0.0001);
        // 75 - 33,33 = 41,67 Prozentpunkte, mehr als die 20 der Konfiguration.
        $this->assertEqualsWithDelta(41.6667, $loudRow['deviation_points'], 0.001);
        $this->assertTrue($loudRow['flagged']);

        $quietRow = $rows->get($quiet->getKey());
        // 8 Leads, alle entschieden, davon 1 nicht erreichbar -> 12,5 Prozent.
        $this->assertEqualsWithDelta(0.125, $quietRow['unreachable_rate'], 0.0001);
        $this->assertFalse($quietRow['flagged']);

        // Auffaellige zuerst.
        $this->assertSame($loud->getKey(), $summary['rows'][0]['buyer_tenant_id']);
    }

    public function test_the_period_filter_looks_at_resolved_at(): void
    {
        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $buyer = Tenant::factory()->create(['type' => TenantType::BUYER]);

        $this->purchases($operator, $buyer, LeadContactStatus::UNREACHABLE, 1, now()->subMonths(2));
        $this->purchases($operator, $buyer, LeadContactStatus::BILLABLE, 1, now()->subDay());

        $summary = app(LeadReachabilityService::class)->summary(now()->subWeek(), now());

        $this->assertSame(1, $summary['leads_total']);
        $this->assertSame(1, $summary['billable']);
        $this->assertSame(0, $summary['unreachable']);
        $this->assertEqualsWithDelta(0.0, $summary['average_rate'], 0.0001);
    }

    private function purchases(
        Tenant $operator,
        Tenant $buyer,
        LeadContactStatus $status,
        int $count,
        ?Carbon $resolvedAt = null,
    ): void {
        for ($i = 0; $i < $count; $i++) {
            $lead = Lead::factory()->inState(LeadState::VERKAUFT)->create([
                'tenant_id' => $operator->getKey(),
                'contact_status' => $status->value,
                'resolved_at' => $status->isResolved() ? ($resolvedAt ?? now()) : null,
            ]);

            LeadPurchase::factory()->create([
                'lead_id' => $lead->getKey(),
                'buyer_tenant_id' => $buyer->getKey(),
            ]);
        }
    }
}
