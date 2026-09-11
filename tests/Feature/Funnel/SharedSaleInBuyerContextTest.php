<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PurchaseLead;
use App\Constants\FunnelStatus;
use App\Constants\LeadState;
use App\Constants\SaleMode;
use App\Constants\TenantType;
use App\Constants\WalletTransactionType;
use App\Models\BuyerRegistration;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\FeatureTest;

/**
 * FB-055a: Der Mehrfachverkauf im echten Kaeufer-Kontext.
 *
 * Die Tests aus FB-055 liefen ohne gesetzten Mandanten und haben deshalb eine
 * Welt geprueft, die es in Produktion nicht gibt: Ohne Filament-Mandanten
 * greift der Mandanten-Scope aus FB-010 nicht, und `$lead->funnel` lieferte
 * brav den Funnel. Im Betrieb zielt der Scope dagegen auf die tenant_id des
 * KAEUFERS, waehrend der Funnel dem BETREIBER gehoert -- also kam null zurueck,
 * die Verkaufsart wurde als `exclusive` gelesen, und der Mehrfachverkauf griff
 * nie.
 *
 * Dieser Test setzt den Mandanten deshalb ausdruecklich. Das ist der ganze
 * Punkt: Ein Test im falschen Kontext prueft nicht zu wenig, er prueft etwas
 * anderes.
 */
class SharedSaleInBuyerContextTest extends FeatureTest
{
    /**
     * Der schwerere der beiden Funde: Es ging nicht nur der Mehrfachverkauf
     * nicht, es ging ueberhaupt kein Kauf.
     *
     * Im Kaeufer-Kontext zielt der Mandanten-Scope aus FB-010 auf die
     * tenant_id des Kaeufers, waehrend der Lead dem Betreiber gehoert. Die
     * Sperrabfrage in LeadStateService::transition() fand ihn deshalb nicht und
     * firstOrFail() warf -- FB-054 war in Produktion vollstaendig unbenutzbar.
     * Gemerkt hat es niemand, weil alle Tests den Kauf ohne gesetzten
     * Filament-Mandanten aufriefen.
     */
    public function test_an_ordinary_purchase_goes_through_while_a_buyer_is_in_context(): void
    {
        Mail::fake();

        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);

        // Bewusst der Regelfall: exklusiver Verkauf, wie er heute ueberall
        // eingestellt ist. Auch er war betroffen.
        $funnel = Funnel::factory()->create([
            'tenant_id' => $operator->getKey(),
            'status' => FunnelStatus::PUBLISHED,
            'lead_price' => 15.00,
        ]);

        $lead = Lead::factory()->inState(LeadState::VERFUEGBAR)->create([
            'tenant_id' => $operator->getKey(),
            'funnel_id' => $funnel->getKey(),
            'score' => 12,
            'price_at_creation' => 15.00,
        ]);

        [$buyer, $user] = $this->approvedBuyer();

        $this->actingAs($user);
        Filament::setTenant($buyer);

        $purchase = app(PurchaseLead::class)->handle($buyer, $lead->fresh(), $user);

        $this->assertSame(1500, $purchase->price_cents);
        $this->assertSame(LeadState::VERKAUFT, $lead->fresh()->lead_state);

        // Der Kaufpreis ist auf dem Kaeufer-Wallet geblockt.
        $this->assertSame(6000, (int) Wallet::forBuyer($buyer)->refresh()->available_cents);
    }

    public function test_a_shared_lead_is_sold_more_than_once_while_a_buyer_is_in_context(): void
    {
        Mail::fake();

        // Der Preis des Verkaeufers gilt jedem Kaeufer gegenueber, auch im
        // Mehrfachverkauf: Einen eigenen Anteilspreis kennt die Geldseite seit
        // LP-WALLET-003 nicht mehr.
        $operator = Tenant::factory()->create([
            'type' => TenantType::OPERATOR,
            'lead_price_cents' => 600,
        ]);

        $funnel = Funnel::factory()->create([
            'tenant_id' => $operator->getKey(),
            'status' => FunnelStatus::PUBLISHED,
            'lead_price' => 15.00,
            'sale_mode' => SaleMode::SHARED,
            'max_buyers' => 2,
        ]);

        $lead = Lead::factory()->inState(LeadState::VERFUEGBAR)->create([
            'tenant_id' => $operator->getKey(),
            'funnel_id' => $funnel->getKey(),
            'score' => 12,
            'price_at_creation' => 15.00,
        ]);

        [$first, $firstUser] = $this->approvedBuyer();
        [$second, $secondUser] = $this->approvedBuyer();

        // Der entscheidende Unterschied zu den FB-055-Tests: Der Kaeufer ist im
        // Kontext, also greift der Mandanten-Scope so wie im Betrieb.
        $this->actingAs($firstUser);
        Filament::setTenant($first);

        $purchase = app(PurchaseLead::class)->handle($first, $lead->fresh(), $firstUser);

        $this->assertSame(600, $purchase->price_cents);

        // Und ein Platz ist noch frei, der Lead bleibt im Angebot. Waere der
        // Funnel nicht erreichbar gewesen, haette effectiveMaxBuyers() 1
        // geliefert und der Lead waere jetzt verkauft.
        $this->assertSame(LeadState::VERFUEGBAR, $lead->fresh()->lead_state);

        $this->actingAs($secondUser);
        Filament::setTenant($second);

        app(PurchaseLead::class)->handle($second, $lead->fresh(), $secondUser);

        $this->assertSame(LeadState::VERKAUFT, $lead->fresh()->lead_state);
        $this->assertSame(2, LeadPurchase::query()->where('lead_id', $lead->getKey())->count());
    }

    /**
     * Der Preis wird im Kaufbeleg festgeschrieben (Architekturleitsatz 4).
     *
     * Massgeblich ist seit LP-WALLET-003 der Preis, den der Verkaeufer im
     * Moment des Kaufs verlangt -- nicht mehr der beim Anlegen des Leads
     * festgehaltene. Was danach am Verkaeufer geaendert wird, erreicht einen
     * bereits geschriebenen Beleg nicht mehr.
     */
    public function test_a_later_price_change_does_not_reach_an_existing_receipt(): void
    {
        Mail::fake();

        $operator = Tenant::factory()->create([
            'type' => TenantType::OPERATOR,
            'lead_price_cents' => 600,
        ]);

        $funnel = Funnel::factory()->create([
            'tenant_id' => $operator->getKey(),
            'status' => FunnelStatus::PUBLISHED,
            'sale_mode' => SaleMode::SHARED,
            'max_buyers' => 3,
        ]);

        $lead = Lead::factory()->inState(LeadState::VERFUEGBAR)->create([
            'tenant_id' => $operator->getKey(),
            'funnel_id' => $funnel->getKey(),
            'score' => 12,
            'price_at_creation' => 15.00,
        ]);

        [$buyer, $user] = $this->approvedBuyer();

        $this->actingAs($user);
        Filament::setTenant($buyer);

        $purchase = app(PurchaseLead::class)->handle($buyer, $lead->fresh(), $user);

        $this->assertSame(600, $purchase->price_cents);

        // Danach hebt der Verkaeufer seinen Preis an.
        $operator->update(['lead_price_cents' => 900]);

        // Der Beleg bleibt, wie er war -- und die Reservierung auch.
        $this->assertSame(600, $purchase->fresh()->price_cents);
        $this->assertSame(600, (int) Wallet::forBuyer($buyer)->refresh()->reserved_cents);
    }

    /**
     * @return array{Tenant, User}
     */
    private function approvedBuyer(): array
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
}
