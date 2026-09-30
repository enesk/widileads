<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PurchaseLead;
use App\Constants\FunnelStatus;
use App\Constants\LeadState;
use App\Constants\TenancyPermissionConstants;
use App\Constants\TenantType;
use App\Constants\WalletTransactionType;
use App\Models\BuyerProfile;
use App\Models\BuyerRegistration;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\LeadPurchase;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AutoLeadPurchaseService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\FeatureTest;

/**
 * Ticket #1: Der Kauf gehoert einem Nutzer, nicht nur einem Mandanten.
 *
 * Das ist Mandanten- und Kollegentrennung: Wer im Kaeufer-Mandanten nur seine
 * eigenen Kaeufe sehen soll, kann das erst, wenn am Beleg steht, wer geklickt
 * hat. Geprueft wird deshalb beides -- dass der Nutzer geschrieben wird, wo
 * einer gehandelt hat, und dass keiner geschrieben wird, wo keiner gehandelt
 * hat.
 */
class LeadPurchaseAttributionTest extends FeatureTest
{
    private Tenant $operator;

    private Funnel $funnel;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->operator = Tenant::factory()->create([
            'type' => TenantType::OPERATOR,
            'lead_price_cents' => 1500,
        ]);

        $this->funnel = Funnel::factory()->create([
            'tenant_id' => $this->operator->getKey(),
            'status' => FunnelStatus::PUBLISHED,
            'lead_price' => 15.00,
        ]);
    }

    private function lead(string $postalCode = '76131'): Lead
    {
        $lead = Lead::factory()->inState(LeadState::VERFUEGBAR)->create([
            'tenant_id' => $this->operator->getKey(),
            'funnel_id' => $this->funnel->getKey(),
            'score' => 12,
            'price_at_creation' => 15.00,
        ]);

        LeadAnswer::query()->create(['lead_id' => $lead->getKey(), 'field_key' => 'plz', 'value' => $postalCode]);

        return $lead;
    }

    private function approvedBuyer(int $balanceCents = 0): Tenant
    {
        $tenant = BuyerRegistration::factory()->approved()->create()->tenant->fresh();

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

    private function memberOf(Tenant $tenant, bool $asAdmin = false): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user);

        if ($asAdmin) {
            $user->tenants()->where('tenant_id', $tenant->getKey())->first()->pivot->assignRole($this->tenantAdminRole());
        }

        return $user;
    }

    private function tenantAdminRole(): Role
    {
        return Role::query()
            ->withoutGlobalScopes()
            ->where('name', TenancyPermissionConstants::ROLE_ADMIN)
            ->where('is_tenant_role', true)
            ->sole();
    }

    public function test_a_purchase_through_the_marketplace_records_the_acting_user(): void
    {
        $buyer = $this->approvedBuyer(balanceCents: 7500);
        $actor = $this->memberOf($buyer);
        $lead = $this->lead();

        $purchase = app(PurchaseLead::class)->handle($buyer, $lead, $actor, 1500);

        $this->assertSame($actor->getKey(), $purchase->purchased_by_user_id);
        $this->assertSame($actor->getKey(), $purchase->fresh()->purchasedBy->getKey());
    }

    public function test_an_automatic_purchase_records_no_user(): void
    {
        $buyer = $this->approvedBuyer(balanceCents: 7500);
        $this->memberOf($buyer, asAdmin: true);

        BuyerProfile::query()->create([
            'tenant_id' => $buyer->getKey(),
            'auto_buy' => true,
        ]);

        $this->lead();

        $this->assertSame(1, app(AutoLeadPurchaseService::class)->run());

        // Der Autokauf hat keinen Handelnden -- auch nicht den Admin. Er hat
        // nicht gekauft, das Kaufprofil hat gekauft.
        $this->assertNull(LeadPurchase::query()->where('buyer_tenant_id', $buyer->getKey())->sole()->purchased_by_user_id);
    }

    public function test_of_user_shows_only_the_own_purchases_while_of_buyer_keeps_the_whole_tenant(): void
    {
        $buyer = $this->approvedBuyer(balanceCents: 15000);
        $first = $this->memberOf($buyer);
        $second = $this->memberOf($buyer);

        $action = app(PurchaseLead::class);
        $action->handle($buyer, $this->lead(), $first, 1500);
        $action->handle($buyer, $this->lead(), $second, 1500);
        $action->handle($buyer, $this->lead(), $second, 1500);

        $this->assertSame(1, LeadPurchase::query()->ofBuyer($buyer)->ofUser($first)->count());
        $this->assertSame(2, LeadPurchase::query()->ofBuyer($buyer)->ofUser($second)->count());
        $this->assertSame(3, LeadPurchase::query()->ofBuyer($buyer)->count());
    }

    /**
     * Der Altbestand geht an den aeltesten Admin -- und zwar an den des
     * eigenen Mandanten.
     *
     * Die Migration selbst laeuft im Test gegen eine leere Datenbank; der
     * Altbestand entsteht erst danach. Geprueft wird deshalb die Zuschreibung
     * der Migration gegen Kaeufe, die sie vorgefunden haette.
     */
    public function test_existing_purchases_go_to_the_oldest_admin_of_their_own_tenant(): void
    {
        $withAdmins = $this->approvedBuyer(balanceCents: 7500);
        $oldestAdmin = $this->memberOf($withAdmins, asAdmin: true);
        $this->memberOf($withAdmins, asAdmin: true);

        $withoutAdmin = $this->approvedBuyer(balanceCents: 7500);
        $this->memberOf($withoutAdmin);

        $action = app(PurchaseLead::class);
        $ownPurchase = $action->handle($withAdmins, $this->lead(), $this->memberOf($withAdmins), 1500);
        $foreignPurchase = $action->handle($withoutAdmin, $this->lead(), $this->memberOf($withoutAdmin), 1500);

        // Stand vor der Migration: kein Kauf kennt seinen Nutzer.
        DB::table('lead_purchases')->update(['purchased_by_user_id' => null]);

        $migration = require database_path('migrations/2026_09_30_153418_add_purchased_by_user_id_to_lead_purchases_table.php');
        $migration->assignExistingPurchasesToTenantAdmins();

        $this->assertSame($oldestAdmin->getKey(), $ownPurchase->fresh()->purchased_by_user_id);

        // Ohne Admin im Mandanten bleibt der Kauf ohne Nutzer -- ein Nutzer
        // aus einem fremden Mandanten kommt nicht in Frage.
        $this->assertNull($foreignPurchase->fresh()->purchased_by_user_id);
    }
}
