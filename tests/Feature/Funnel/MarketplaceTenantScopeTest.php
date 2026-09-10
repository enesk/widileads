<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\FunnelStatus;
use App\Constants\LeadState;
use App\Constants\TenantType;
use App\Filament\Dashboard\Pages\BuyerProfile as BuyerProfilePage;
use App\Marketplace\MarketplaceCatalog;
use App\Marketplace\MarketplaceListing;
use App\Models\BuyerRegistration;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Tests\Feature\FeatureTest;

/**
 * FB-091: Der Marktplatz haelt auch dann, wenn beide Mandanten-Scopes aktiv sind.
 *
 * Es gibt zwei globale Mandanten-Scopes: unseren eigenen aus BelongsToTenant und
 * je Filament-Panel einen zweiten, den Filament fuer jede eingeschraenkte
 * Resource registriert. Der zweite entsteht erst beim Booten des Panels --
 * deshalb bootet dieser Test es ausdruecklich. Ohne das lief der Marktplatz in
 * den uebrigen Tests mit nur einem Scope, war gruen und im Browser trotzdem
 * leer.
 *
 * Geprueft wird die Mandantentrennung in beide Richtungen: Der Kaeufer sieht das
 * Angebot des Betreibers, aber nichts von einem anderen Kaeufer.
 */
class MarketplaceTenantScopeTest extends FeatureTest
{
    /**
     * Der Zustand eines echten Requests: Panel gebootet, Kaeufer im Kontext.
     *
     * @return array{Tenant, User}
     */
    private function bootedBuyerContext(): array
    {
        $registration = BuyerRegistration::factory()->approved()->create();
        $tenant = $registration->tenant->fresh();

        $user = User::factory()->create();
        $tenant->users()->attach($user);

        $this->actingAs($user);

        // Ein echter Aufruf der Kaeufer-Strecke. Erst dabei bootet Filament das
        // Panel und registriert den panel-eigenen Mandanten-Scope -- setzt man
        // Panel und Mandant nur von Hand, entsteht er nie, und der Test liefe
        // gegen eine Lage, die es im Browser nicht gibt.
        $this->get(BuyerProfilePage::getUrl(panel: 'dashboard', tenant: $tenant))->assertSuccessful();

        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant);

        return [$tenant, $user];
    }

    private function publishedFunnelOfOperator(string $name): Funnel
    {
        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);

        return Funnel::factory()->create([
            'tenant_id' => $operator->getKey(),
            'name' => $name,
            'status' => FunnelStatus::PUBLISHED,
        ]);
    }

    public function test_the_catalog_lists_operator_funnels_while_both_tenant_scopes_are_active(): void
    {
        $funnel = $this->publishedFunnelOfOperator('Sichere dein Tier jetzt ab');

        $this->bootedBuyerContext();

        // Die Vorbedingung des Tests: Beide Scopes haengen wirklich am Modell.
        // Faellt eine dieser Zusagen weg, prueft der Test nicht mehr, wofuer er
        // geschrieben wurde.
        $this->assertTrue(Funnel::hasGlobalScope('tenant'));
        $this->assertTrue(Funnel::hasGlobalScope(Filament::getPanel('dashboard')->getTenancyScopeName()));

        $this->assertSame(
            [$funnel->getKey() => 'Sichere dein Tier jetzt ab'],
            app(MarketplaceCatalog::class)->publishedFunnels(),
        );
    }

    public function test_the_listing_shows_operator_leads_while_both_tenant_scopes_are_active(): void
    {
        $funnel = $this->publishedFunnelOfOperator('Sichere dein Tier jetzt ab');

        $lead = Lead::factory()->inState(LeadState::VERFUEGBAR)->create([
            'tenant_id' => $funnel->tenant_id,
            'funnel_id' => $funnel->getKey(),
            'score' => 10,
        ]);

        [$tenant] = $this->bootedBuyerContext();

        $listed = app(MarketplaceListing::class)->for($tenant, null);

        $this->assertSame([$lead->getKey()], $listed->modelKeys());

        // Der Fragebogenname gehoert dem Betreiber -- er darf beim Nachladen
        // nicht am Mandanten-Scope haengenbleiben (FB-055a).
        $this->assertSame('Sichere dein Tier jetzt ab', $listed->first()->funnel?->name);
    }

    public function test_the_listing_hides_leads_of_another_buyer(): void
    {
        $otherBuyer = Tenant::factory()->create(['type' => TenantType::BUYER]);

        Lead::factory()->inState(LeadState::VERFUEGBAR)->create([
            'tenant_id' => $otherBuyer->getKey(),
            'funnel_id' => null,
            'score' => 10,
        ]);

        [$tenant] = $this->bootedBuyerContext();

        $this->assertTrue(app(MarketplaceListing::class)->for($tenant, null)->isEmpty());
    }
}
