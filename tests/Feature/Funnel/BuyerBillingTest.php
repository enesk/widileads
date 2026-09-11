<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\LeadState;
use App\Constants\PurchaseStatus;
use App\Constants\TenantType;
use App\Constants\WalletTransactionType;
use App\Models\BuyerRegistration;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\Wallet;
use App\Services\BuyerBillingService;
use App\Services\Wallet\WalletService;
use Tests\Feature\FeatureTest;

/**
 * FB-059, seit LP-WALLET-022 gegen das Wallet: Die Monatsabrechnung muss mit
 * dem Wallet-Ledger uebereinstimmen.
 *
 * Das ist der eigentliche Zweck der Uebersicht: Sie stellt zwei unabhaengig
 * gefuehrte Rechnungen nebeneinander -- die Kaufbelege und das Ledger. Weichen
 * sie voneinander ab, ist etwas kaputt, und zwar an einer Stelle, die Geld
 * betrifft.
 */
class BuyerBillingTest extends FeatureTest
{
    public function test_the_statement_matches_the_wallet_ledger(): void
    {
        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $buyer = BuyerRegistration::factory()->approved()->create()->tenant->fresh();

        $wallets = app(WalletService::class);
        $wallet = Wallet::forBuyer($buyer);

        // 150,00 EUR aufgeladen.
        $wallets->post($wallet, WalletTransactionType::TOPUP, 15000, 'Aufladung');

        // Drei Leads gekauft und abgerechnet, in drei verschiedenen Zustaenden.
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

            $wallets->post(
                wallet: $wallet,
                type: WalletTransactionType::CAPTURE,
                amountCents: -1500,
                description: 'Abbuchung',
                reference: $purchase,
            );

            $purchases[] = $purchase;
        }

        // Einer davon wurde erstattet.
        $purchases[2]->forceFill(['status' => PurchaseStatus::REFUNDED, 'refunded_at' => now()])->save();
        $wallets->post(
            wallet: $wallet,
            type: WalletTransactionType::REFUND,
            amountCents: 1500,
            description: 'Erstattung',
            reference: $purchases[2],
        );

        $statement = app(BuyerBillingService::class)->statementFor($buyer, now());

        // Die Leadseite.
        $this->assertSame(3, $statement['purchases']);
        $this->assertSame(1, $statement['states'][LeadState::VERKAUFT->value]);
        $this->assertSame(1, $statement['states'][LeadState::ERREICHT->value]);
        $this->assertSame(1, $statement['states'][LeadState::UNERREICHBAR->value]);
        $this->assertSame(4500, $statement['revenue_cents']);

        // Die Geldseite -- und die Probe, um die es geht: Was abgebucht wurde,
        // muss der Summe der abgerechneten Kaufpreise entsprechen.
        $this->assertSame(15000, $statement['topped_up_cents']);
        $this->assertSame(4500, $statement['captured_cents']);
        $this->assertSame(1500, $statement['refunded_cents']);
        $this->assertSame($statement['captured_cents'], $statement['captured_expected_cents']);

        // Und der Saldo ergibt sich aus denselben Zahlen.
        $this->assertSame(
            $statement['topped_up_cents'] - $statement['captured_cents'] + $statement['refunded_cents'],
            (int) $wallet->fresh()->balance_cents,
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
