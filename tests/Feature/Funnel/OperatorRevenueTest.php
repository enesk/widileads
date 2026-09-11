<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\LeadState;
use App\Constants\TenantType;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Services\OperatorRevenueReport;
use Tests\Feature\FeatureTest;

/**
 * FB-072: Umsatzuebersicht des Betreibers.
 *
 * Hier geht es um Geld, deshalb steht es unter Test: Eine Uebersicht, die
 * falsch summiert, faellt niemandem auf, bis sie nicht mehr zur Buchhaltung
 * passt. Geprueft werden die Summen und die Mandantentrennung -- ein Betreiber
 * darf die Umsaetze eines anderen nicht sehen.
 */
class OperatorRevenueTest extends FeatureTest
{
    private function operator(): Tenant
    {
        return Tenant::factory()->create(['type' => TenantType::OPERATOR]);
    }

    private function buyer(string $name): Tenant
    {
        return Tenant::factory()->create(['type' => TenantType::BUYER, 'name' => $name]);
    }

    private function soldLead(Tenant $operator, ?Funnel $funnel, Tenant $buyer, int $priceCents, bool $refunded = false): LeadPurchase
    {
        $lead = Lead::factory()->inState(LeadState::VERKAUFT)->create([
            'tenant_id' => $operator->id,
            'funnel_id' => $funnel?->id,
        ]);

        return LeadPurchase::factory()->when($refunded, fn ($factory) => $factory->refunded())->create([
            'lead_id' => $lead->id,
            'buyer_tenant_id' => $buyer->id,
            'seller_tenant_id' => $operator->id,
            'price_cents' => $priceCents,
            'purchased_at' => now(),
        ]);
    }

    public function test_revenue_and_refunds_add_up_per_funnel_and_per_buyer(): void
    {
        $operator = $this->operator();
        $pfotencheck = Funnel::factory()->create(['tenant_id' => $operator->id, 'name' => 'Pfotencheck']);
        $zahncheck = Funnel::factory()->create(['tenant_id' => $operator->id, 'name' => 'Zahncheck']);

        $agenturNord = $this->buyer('Agentur Nord');
        $agenturSued = $this->buyer('Agentur Sued');

        $this->soldLead($operator, $pfotencheck, $agenturNord, 1500);
        $this->soldLead($operator, $pfotencheck, $agenturNord, 1500);
        // Eine anerkannte Reklamation (FB-058) setzt den Kaufbeleg auf
        // `refunded`; der Erloes geht dem Betreiber wieder ab.
        $this->soldLead($operator, $pfotencheck, $agenturSued, 1500, refunded: true);
        $this->soldLead($operator, $zahncheck, $agenturSued, 2000);

        $report = app(OperatorRevenueReport::class)->for($operator);

        // Gerechnet wird im Erloes des Verkaeufers, also nach 20 % Provision:
        // aus 15,00 werden 12,00, aus 20,00 werden 16,00.
        // Gesamt: vier Verkaeufe, 52,00 EUR Erloes, eine Gutschrift ueber 12,00.
        $this->assertCount(1, $report['totals']);
        $this->assertSame(4, $report['totals'][0]['sold']);
        $this->assertSame(5200, $report['totals'][0]['revenue_cents']);
        $this->assertSame(1, $report['totals'][0]['refunds']);
        $this->assertSame(1200, $report['totals'][0]['refunded_cents']);
        $this->assertSame(4000, $report['totals'][0]['net_cents']);

        $byFunnel = collect($report['by_funnel'])->keyBy('label');

        $this->assertSame(3, $byFunnel['Pfotencheck']['sold']);
        $this->assertSame(3600, $byFunnel['Pfotencheck']['revenue_cents']);
        $this->assertSame(1200, $byFunnel['Pfotencheck']['refunded_cents']);
        $this->assertSame(2400, $byFunnel['Pfotencheck']['net_cents']);

        $this->assertSame(1, $byFunnel['Zahncheck']['sold']);
        $this->assertSame(1600, $byFunnel['Zahncheck']['net_cents']);

        $byBuyer = collect($report['by_buyer'])->keyBy('label');

        $this->assertSame(2400, $byBuyer['Agentur Nord']['net_cents']);
        // Zwei Kaeufe (12,00 + 16,00 Erloes), davon einer gutgeschrieben.
        $this->assertSame(2800, $byBuyer['Agentur Sued']['revenue_cents']);
        $this->assertSame(1600, $byBuyer['Agentur Sued']['net_cents']);
    }

    public function test_an_operator_never_sees_the_revenue_of_another(): void
    {
        $mine = $this->operator();
        $foreign = $this->operator();
        $buyer = $this->buyer('Agentur Nord');

        $this->soldLead($mine, Funnel::factory()->create(['tenant_id' => $mine->id]), $buyer, 1500);
        $this->soldLead($foreign, Funnel::factory()->create(['tenant_id' => $foreign->id]), $buyer, 9900);

        $report = app(OperatorRevenueReport::class)->for($mine);

        $this->assertSame(1, $report['totals'][0]['sold']);
        $this->assertSame(1200, $report['totals'][0]['revenue_cents']);
    }
}
