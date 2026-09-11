<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\FunnelStatus;
use App\Constants\LeadState;
use App\Constants\TenantType;
use App\Constants\WalletTransactionType;
use App\Models\BuyerProfile;
use App\Models\BuyerRegistration;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AutoLeadPurchaseService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\FeatureTest;

/**
 * FB-056: Autokauf.
 *
 * Geld ohne einen Menschen davor: Der Job bucht ab, ohne dass jemand klickt.
 * Geprueft wird deshalb, dass er nur kauft, was das Profil vorsieht, dass das
 * Tageslimit haelt, und dass ein leeres Guthaben ihn stoppt statt ins Minus zu
 * laufen.
 */
class AutoLeadPurchaseTest extends FeatureTest
{
    private Tenant $operator;

    private Funnel $funnel;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $this->funnel = Funnel::factory()->create([
            'tenant_id' => $this->operator->getKey(),
            'status' => FunnelStatus::PUBLISHED,
            'lead_price' => 15.00,
        ]);
    }

    private function lead(string $postalCode = '76131', int $score = 12): Lead
    {
        $lead = Lead::factory()->inState(LeadState::VERFUEGBAR)->create([
            'tenant_id' => $this->operator->getKey(),
            'funnel_id' => $this->funnel->getKey(),
            'score' => $score,
            'price_at_creation' => 15.00,
        ]);

        LeadAnswer::query()->create(['lead_id' => $lead->getKey(), 'field_key' => 'plz', 'value' => $postalCode]);
        LeadAnswer::query()->create(['lead_id' => $lead->getKey(), 'field_key' => 'tierart', 'value' => 'hund']);

        return $lead;
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    private function autoBuyer(int $balanceCents, array $criteria = []): Tenant
    {
        $tenant = BuyerRegistration::factory()->approved()->create()->tenant->fresh();
        $tenant->users()->attach(User::factory()->create());

        BuyerProfile::query()->create(array_merge([
            'tenant_id' => $tenant->getKey(),
            'auto_buy' => true,
        ], $criteria));

        if ($balanceCents > 0) {
            app(WalletService::class)->post(
                wallet: Wallet::forBuyer($tenant),
                type: WalletTransactionType::TOPUP,
                amountCents: $balanceCents,
                description: 'Aufladung im Test',
            );
        }

        return $tenant;
    }

    public function test_the_daily_limit_caps_what_the_job_buys(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->lead();
        }

        $buyer = $this->autoBuyer(balanceCents: 7500, criteria: ['daily_limit' => 2]);

        $bought = app(AutoLeadPurchaseService::class)->run();

        $this->assertSame(2, $bought);
        $this->assertSame(2, LeadPurchase::query()->where('buyer_tenant_id', $buyer->getKey())->count());
        $this->assertSame(4500, (int) Wallet::forBuyer($buyer)->refresh()->available_cents);

        // Ein zweiter Lauf am selben Tag kauft nichts mehr dazu -- sonst waere
        // das Tageslimit nur ein Limit je Lauf.
        $this->assertSame(0, app(AutoLeadPurchaseService::class)->run());
        $this->assertSame(2, LeadPurchase::query()->where('buyer_tenant_id', $buyer->getKey())->count());
    }

    public function test_the_job_buys_only_what_the_profile_matches_and_stops_when_the_balance_runs_out(): void
    {
        $matching = $this->lead(postalCode: '76131');
        $wrongRegion = $this->lead(postalCode: '10115');
        $secondMatching = $this->lead(postalCode: '76200');

        // Guthaben fuer genau einen Kauf, aber zwei passende Leads.
        $buyer = $this->autoBuyer(balanceCents: 1500, criteria: ['postal_prefixes' => ['76']]);

        $bought = app(AutoLeadPurchaseService::class)->run();

        $this->assertSame(1, $bought);
        $this->assertSame(0, (int) Wallet::forBuyer($buyer)->refresh()->available_cents);

        // Der Lead ausserhalb der Region wurde nicht angefasst.
        $this->assertSame(LeadState::VERFUEGBAR, $wrongRegion->fresh()->lead_state);

        // Und der zweite passende Lead haengt nicht in einer Reservierung fest,
        // die der abgebrochene Versuch hinterlassen haette.
        $stillAvailable = [$matching->fresh(), $secondMatching->fresh()];
        $sold = array_filter($stillAvailable, static fn (Lead $lead): bool => $lead->lead_state === LeadState::VERKAUFT);

        $this->assertCount(1, $sold);

        foreach ($stillAvailable as $lead) {
            $this->assertNotSame(LeadState::RESERVIERT, $lead->lead_state);
        }
    }
}
