<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PurchaseLead;
use App\Constants\FunnelStatus;
use App\Constants\LeadState;
use App\Constants\SaleMode;
use App\Constants\TenantType;
use App\Exceptions\LeadNotPurchasableException;
use App\Models\BuyerRegistration;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreditLedgerService;
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
    private function sharedLead(int $maxBuyers = 3, ?float $sharedPrice = null): Lead
    {
        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);

        $funnel = Funnel::factory()->create([
            'tenant_id' => $operator->getKey(),
            'status' => FunnelStatus::PUBLISHED,
            'lead_price' => 15.00,
            'sale_mode' => SaleMode::SHARED,
            'max_buyers' => $maxBuyers,
            'shared_price' => $sharedPrice,
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

        app(CreditLedgerService::class)->purchase($tenant, 5, 7500);

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

    public function test_each_buyer_of_a_shared_lead_pays_the_shared_price_and_only_once(): void
    {
        Mail::fake();

        // Eigener Anteilspreis am Funnel -- er gilt, nicht der Exklusivpreis
        // und nicht der beim Anlegen des Leads festgehaltene.
        $lead = $this->sharedLead(maxBuyers: 3, sharedPrice: 6.00);

        [$buyer, $user] = $this->buyer();

        $purchase = app(PurchaseLead::class)->handle($buyer, $lead->fresh(), $user);

        $this->assertSame(600, $purchase->price_cents);

        // Ein Guthaben je Kauf, unabhaengig von der Verkaufsart: Das Guthaben
        // ist die Einheit "ein Lead", der Preis die Geldgroesse daneben.
        $this->assertSame(4, app(CreditLedgerService::class)->balanceFor($buyer->fresh()));

        // Derselbe Kaeufer kauft denselben Lead nicht zweimal -- das waere fuer
        // ihn nichts als eine zweite Abbuchung.
        try {
            app(PurchaseLead::class)->handle($buyer, $lead->fresh(), $user);
            $this->fail('Ein zweiter Kauf desselben Leads muss abgewiesen werden.');
        } catch (LeadNotPurchasableException) {
            // erwartet
        }

        $this->assertSame(1, LeadPurchase::query()->where('lead_id', $lead->getKey())->count());
        $this->assertSame(4, app(CreditLedgerService::class)->balanceFor($buyer->fresh()));
    }
}
