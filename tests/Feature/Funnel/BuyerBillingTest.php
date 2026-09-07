<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\LeadState;
use App\Constants\TenantType;
use App\Models\BuyerRegistration;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Services\BuyerBillingService;
use App\Services\CreditLedgerService;
use Tests\Feature\FeatureTest;

/**
 * FB-059: Die Monatsabrechnung muss mit dem Guthabenkonto uebereinstimmen.
 *
 * Das ist der eigentliche Zweck der Uebersicht: Sie stellt zwei unabhaengig
 * gefuehrte Rechnungen nebeneinander -- die Kaufbelege und das Journal. Weichen
 * sie voneinander ab, ist etwas kaputt, und zwar an einer Stelle, die Geld
 * betrifft.
 */
class BuyerBillingTest extends FeatureTest
{
    public function test_the_statement_matches_the_credit_ledger(): void
    {
        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $buyer = BuyerRegistration::factory()->approved()->create()->tenant->fresh();

        $credits = app(CreditLedgerService::class);

        // Zehn Guthaben gekauft.
        $credits->purchase($buyer, 10, 15000);

        // Drei Leads gekauft, in drei verschiedenen Zustaenden.
        $states = [LeadState::VERKAUFT, LeadState::ERREICHT, LeadState::UNERREICHBAR];
        $purchases = [];

        foreach ($states as $state) {
            $lead = Lead::factory()->inState($state)->create([
                'tenant_id' => $operator->getKey(),
                'score' => 10,
            ]);

            $purchase = LeadPurchase::factory()->create([
                'lead_id' => $lead->getKey(),
                'buyer_tenant_id' => $buyer->getKey(),
                'price_cents' => 1500,
                'purchased_at' => now(),
            ]);

            $credits->debit($buyer, 1, $purchase);
            $purchases[] = $purchase;
        }

        // Einer davon wurde gutgeschrieben.
        $credits->refund($buyer, 1, $purchases[2]);

        $statement = app(BuyerBillingService::class)->statementFor($buyer, now());

        // Die Leadseite.
        $this->assertSame(3, $statement['purchases']);
        $this->assertSame(1, $statement['states'][LeadState::VERKAUFT->value]);
        $this->assertSame(1, $statement['states'][LeadState::ERREICHT->value]);
        $this->assertSame(1, $statement['states'][LeadState::UNERREICHBAR->value]);
        $this->assertSame(4500, $statement['revenue_cents']);

        // Die Guthabenseite -- und der Abgleich, um den es geht: Jeder Kauf hat
        // genau ein Guthaben gekostet.
        $this->assertSame(10, $statement['credits_purchased']);
        $this->assertSame($statement['purchases'], $statement['credits_debited']);
        $this->assertSame(1, $statement['credits_refunded']);

        // Und der Saldo ergibt sich aus denselben Zahlen.
        $this->assertSame(
            $statement['credits_purchased'] - $statement['credits_debited'] + $statement['credits_refunded'],
            $credits->balanceFor($buyer->fresh()),
        );
    }

    public function test_the_statement_covers_only_the_selected_month(): void
    {
        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $buyer = BuyerRegistration::factory()->approved()->create()->tenant->fresh();

        foreach ([now(), now()->subMonthNoOverflow()] as $when) {
            $lead = Lead::factory()->inState(LeadState::VERKAUFT)->create([
                'tenant_id' => $operator->getKey(),
                'score' => 10,
            ]);

            LeadPurchase::factory()->create([
                'lead_id' => $lead->getKey(),
                'buyer_tenant_id' => $buyer->getKey(),
                'price_cents' => 1500,
                'purchased_at' => $when,
            ]);
        }

        $this->assertSame(1, app(BuyerBillingService::class)->statementFor($buyer, now())['purchases']);
        $this->assertSame(
            1,
            app(BuyerBillingService::class)->statementFor($buyer, now()->subMonthNoOverflow())['purchases'],
        );
    }
}
