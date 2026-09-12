<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Constants\LeadState;
use App\Constants\PaymentMode;
use App\Constants\PurchaseStatus;
use App\Constants\TenantType;
use App\Constants\WalletTransactionType;
use App\Models\BuyerRegistration;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Tests\Feature\FeatureTest;

/**
 * `wallet:reset` raeumt die Geldseite ab. Geprueft wird, dass danach nichts
 * mehr uebrig ist, was `wallet:verify` als Abweichung melden wuerde: leeres
 * Journal, Salden auf 0 und keine Reservierung ohne Deckung.
 */
class ResetWalletLedgerTest extends FeatureTest
{
    public function test_it_clears_the_ledger_and_zeroes_every_balance(): void
    {
        [$buyer, $purchase] = $this->aBuyerWithAReservedPurchase();

        $wallet = Wallet::forBuyer($buyer);
        $this->assertSame(1500, $wallet->reserved_cents);

        $this->artisan('wallet:reset', ['--force' => true])->assertExitCode(0);

        $this->assertSame(0, WalletTransaction::query()->count());

        foreach (Wallet::query()->get() as $each) {
            $this->assertSame(0, $each->balance_cents);
            $this->assertSame(0, $each->reserved_cents);
            $this->assertFalse($each->purchase_blocked);
        }

        // Eine Reservierung ohne Deckung im Journal waere genau die Abweichung,
        // die die zweite Klammer von wallet:verify meldet.
        $this->assertSame(PurchaseStatus::RELEASED, $purchase->fresh()->status);
    }

    public function test_the_dry_run_changes_nothing(): void
    {
        [$buyer] = $this->aBuyerWithAReservedPurchase();

        $this->artisan('wallet:reset', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(1500, Wallet::forBuyer($buyer)->reserved_cents);
        $this->assertSame(2, WalletTransaction::query()->count());
    }

    public function test_it_keeps_the_payment_mode_unless_asked(): void
    {
        [$buyer] = $this->aBuyerWithAReservedPurchase();

        Wallet::forBuyer($buyer)->forceFill([
            'payment_mode' => PaymentMode::POSTPAID->value,
            'credit_limit_cents' => 30000,
        ])->save();

        $this->artisan('wallet:reset', ['--force' => true])->assertExitCode(0);

        $wallet = Wallet::forBuyer($buyer);
        $this->assertSame(PaymentMode::POSTPAID, $wallet->payment_mode);
        $this->assertSame(30000, $wallet->credit_limit_cents);

        $this->artisan('wallet:reset', ['--force' => true, '--postpaid' => true])->assertExitCode(0);

        $wallet = Wallet::forBuyer($buyer)->fresh();
        $this->assertSame(PaymentMode::PREPAID, $wallet->payment_mode);
        $this->assertSame(0, $wallet->credit_limit_cents);
    }

    public function test_production_is_locked_without_the_release_switch(): void
    {
        [$buyer] = $this->aBuyerWithAReservedPurchase();

        $this->app['env'] = 'production';
        config(['wallet.allow_reset' => false]);

        // Auch --force oeffnet die Sperre nicht.
        $this->artisan('wallet:reset', ['--force' => true])
            ->expectsOutputToContain('in der Produktionsumgebung gesperrt')
            ->assertExitCode(1);

        $this->assertSame(2, WalletTransaction::query()->count());
        $this->assertSame(1500, Wallet::forBuyer($buyer)->reserved_cents);
    }

    public function test_production_runs_after_typing_the_environment_name(): void
    {
        $this->aBuyerWithAReservedPurchase();

        $this->app['env'] = 'production';
        config(['wallet.allow_reset' => true]);

        $this->artisan('wallet:reset', ['--force' => true])
            ->expectsQuestion('Produktionsumgebung. Zum Bestaetigen "production" eingeben', 'production')
            ->assertExitCode(0);

        $this->assertSame(0, WalletTransaction::query()->count());
    }

    /**
     * @return array{0: Tenant, 1: LeadPurchase}
     */
    private function aBuyerWithAReservedPurchase(): array
    {
        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $buyer = BuyerRegistration::factory()->approved()->create()->tenant->fresh();

        $wallets = app(WalletService::class);
        $wallet = Wallet::forBuyer($buyer);

        $wallets->post($wallet, WalletTransactionType::TOPUP, 15000, 'Aufladung');

        $lead = Lead::factory()->inState(LeadState::VERKAUFT)->create([
            'tenant_id' => $operator->getKey(),
            'score' => 10,
        ]);

        $purchase = LeadPurchase::factory()->create([
            'lead_id' => $lead->getKey(),
            'buyer_tenant_id' => $buyer->getKey(),
            'seller_tenant_id' => $operator->getKey(),
            'price_cents' => 1500,
            'status' => PurchaseStatus::RESERVED,
            'reserved_at' => now(),
            'purchased_at' => now(),
        ]);

        $wallets->post(
            wallet: $wallet,
            type: WalletTransactionType::RESERVE,
            amountCents: 1500,
            description: 'Reservierung',
            reference: $purchase,
        );

        return [$buyer, $purchase];
    }
}
