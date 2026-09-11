<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PurchaseLead;
use App\Constants\FunnelStatus;
use App\Constants\LeadState;
use App\Constants\SaleMode;
use App\Constants\TenantType;
use App\Constants\WalletTransactionType;
use App\Exceptions\LeadNotPurchasableException;
use App\Models\BuyerRegistration;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\FeatureTest;

/**
 * FB-055: Mehrfachverkauf.
 *
 * Geprueft wird, was Geld und Zustand betrifft: Wie oft ein Lead verkauft
 * werden darf, was jeder Kaeufer dabei zahlt, und wann er aus dem Angebot
 * verschwindet. Ein Fehler an dieser Stelle verkauft entweder zu oft oder zum
 * falschen Preis -- beides merkt niemand, bis abgerechnet wird.
 */
class SharedLeadSaleTest extends FeatureTest
{
    private function sharedLead(int $maxBuyers = 3, int $sellerPriceCents = 1500): Lead
    {
        // Der Verkaufspreis steht seit LP-WALLET-003 am Verkaeufer-Mandanten.
        // Die Verkaufsart entscheidet nur noch, wie oft verkauft wird -- einen
        // eigenen Anteilspreis kennt die Geldseite nicht mehr.
        $operator = Tenant::factory()->create([
            'type' => TenantType::OPERATOR,
            'lead_price_cents' => $sellerPriceCents,
        ]);

        $funnel = Funnel::factory()->create([
            'tenant_id' => $operator->getKey(),
            'status' => FunnelStatus::PUBLISHED,
            'lead_price' => 15.00,
            'sale_mode' => SaleMode::SHARED,
            'max_buyers' => $maxBuyers,
        ]);

        return Lead::factory()->inState(LeadState::VERFUEGBAR)->create([
            'tenant_id' => $operator->getKey(),
            'funnel_id' => $funnel->getKey(),
            'score' => 12,
            'price_at_creation' => 15.00,
        ]);
    }

    /**
     * @return array{Tenant, User}
     */
    private function buyer(): array
    {
        $tenant = BuyerRegistration::factory()->approved()->create()->tenant->fresh();
        $user = User::factory()->create();
        $tenant->users()->attach($user);

        app(WalletService::class)->post(
            wallet: Wallet::forBuyer($tenant),
            type: WalletTransactionType::TOPUP,
            amountCents: 7500,
            description: 'Aufladung im Test',
        );

        return [$tenant, $user];
    }

    public function test_a_shared_lead_stays_on_offer_until_the_last_slot_is_taken(): void
    {
        Mail::fake();

        $lead = $this->sharedLead(maxBuyers: 3);
        $action = app(PurchaseLead::class);

        [$first, $firstUser] = $this->buyer();
        [$second, $secondUser] = $this->buyer();
        [$third, $thirdUser] = $this->buyer();

        $action->handle($first, $lead->fresh(), $firstUser);

        // Noch zwei Plaetze frei: Der Lead gehoert wieder ins Angebot, und
        // zwar ohne den Namen des ersten Kaeufers an sich.
        $lead->refresh();
        $this->assertSame(LeadState::VERFUEGBAR, $lead->lead_state);
        $this->assertNull($lead->reserved_by);

        $action->handle($second, $lead->fresh(), $secondUser);

        $this->assertSame(LeadState::VERFUEGBAR, $lead->fresh()->lead_state);

        $action->handle($third, $lead->fresh(), $thirdUser);

        // Der dritte Platz war der letzte -- ab jetzt ist der Lead vergeben.
        $this->assertSame(LeadState::VERKAUFT, $lead->fresh()->lead_state);
        $this->assertSame(3, LeadPurchase::query()->where('lead_id', $lead->getKey())->count());

        // Und ein vierter kommt nicht mehr durch.
        [$fourth, $fourthUser] = $this->buyer();

        $this->expectException(LeadNotPurchasableException::class);
        $action->handle($fourth, $lead->fresh(), $fourthUser);
    }

    public function test_each_buyer_of_a_shared_lead_pays_the_sellers_price_and_only_once(): void
    {
        Mail::fake();

        // Der Preis des Verkaeufers gilt -- und zwar jedem Kaeufer gegenueber
        // derselbe, unabhaengig von der Verkaufsart.
        $lead = $this->sharedLead(maxBuyers: 3, sellerPriceCents: 600);

        [$buyer, $user] = $this->buyer();

        $purchase = app(PurchaseLead::class)->handle($buyer, $lead->fresh(), $user);

        $this->assertSame(600, $purchase->price_cents);

        // Genau einmal geblockt: 6,00 EUR von 75,00 EUR sind reserviert.
        $this->assertSame(6900, (int) Wallet::forBuyer($buyer)->refresh()->available_cents);

        // Derselbe Kaeufer kauft denselben Lead nicht zweimal -- das waere fuer
        // ihn nichts als eine zweite Abbuchung.
        try {
            app(PurchaseLead::class)->handle($buyer, $lead->fresh(), $user);
            $this->fail('Ein zweiter Kauf desselben Leads muss abgewiesen werden.');
        } catch (LeadNotPurchasableException) {
            // erwartet
        }

        $this->assertSame(1, LeadPurchase::query()->where('lead_id', $lead->getKey())->count());
        $this->assertSame(6900, (int) Wallet::forBuyer($buyer)->refresh()->available_cents);
    }
}
