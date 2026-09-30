<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\FunnelFieldKey;
use App\Constants\TenancyPermissionConstants;
use App\Livewire\Portal\PurchasedLeads;
use App\Models\BuyerRegistration;
use App\Models\CallerId;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\LeadPurchase;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * Ticket #2: "Meine Leads" zeigt die eigenen Kaeufe -- und nur die.
 *
 * Geprueft wird die Trennung zwischen Kollegen desselben Mandanten, nicht die
 * Darstellung: die Liste, die ueber die Adresse erreichbare Detailseite und
 * das Handeln daran. Die Detailseite ist der eigentliche Punkt -- ohne
 * Pruefung dort waere die Liste blosse Kosmetik.
 */
class PurchaseVisibilityTest extends FeatureTest
{
    private Tenant $buyer;

    private User $owner;

    private User $colleague;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = BuyerRegistration::factory()->approved()->create()->tenant->fresh();
        $this->owner = $this->memberOfBuyer();
        $this->colleague = $this->memberOfBuyer();
    }

    public function test_meine_leads_zeigt_nur_die_eigenen_kaeufe(): void
    {
        $this->purchase($this->owner, 'Alfacheck');
        $this->purchase($this->colleague, 'Betacheck');
        $this->purchase(null, 'Gammacheck');

        $this->actingAs($this->owner);

        // Den Mandanten setzt sonst die Routen-Middleware; im Komponententest
        // gibt es sie nicht (siehe InteractsWithPortalTenant).
        Filament::setTenant($this->buyer, isQuiet: true);

        Livewire::test(PurchasedLeads::class)
            ->assertSee('Alfacheck')
            ->assertDontSee('Betacheck')
            ->assertDontSee('Gammacheck');
    }

    public function test_die_detailseite_eines_kollegen_bleibt_verschlossen(): void
    {
        $foreign = $this->purchase($this->colleague);

        $this->actingAs($this->owner);

        // Die Basisklasse schaltet die Ausnahmebehandlung ab; fuer eine
        // Statuspruefung braucht es sie.
        $this->withExceptionHandling();

        $this->get($this->detailUrl($foreign))->assertForbidden();
    }

    public function test_mit_dem_recht_team_leads_ist_der_kauf_lesbar_aber_nicht_anzufassen(): void
    {
        $foreign = $this->purchase($this->colleague);

        $this->grantTeamLeads($this->owner);
        $this->actingAs($this->owner);

        $this->assertTrue(Gate::forUser($this->owner)->allows('view', $foreign));
        $this->assertFalse(Gate::forUser($this->owner)->allows('act', $foreign));
    }

    public function test_ein_kauf_ohne_nutzer_gehoert_dem_ganzen_mandanten(): void
    {
        $automatic = $this->purchase(null);

        $this->assertTrue(Gate::forUser($this->owner)->allows('view', $automatic));
        $this->assertTrue(Gate::forUser($this->owner)->allows('act', $automatic));
    }

    public function test_ein_fremder_mandant_kommt_an_nichts(): void
    {
        $own = $this->purchase($this->owner);

        $stranger = BuyerRegistration::factory()->approved()->create()->tenant->fresh();
        $strangerUser = User::factory()->create();
        $stranger->users()->attach($strangerUser);

        $this->assertFalse(Gate::forUser($strangerUser)->allows('view', $own));
    }

    private function detailUrl(LeadPurchase $purchase): string
    {
        return route('portal.leads.show', [
            'tenant' => $this->buyer->uuid,
            'purchase' => $purchase->getKey(),
        ]);
    }

    private function purchase(?User $purchaser, ?string $firstName = null): LeadPurchase
    {
        $lead = Lead::factory()->create();

        if ($firstName !== null) {
            LeadAnswer::query()->create([
                'lead_id' => $lead->getKey(),
                'field_key' => FunnelFieldKey::VORNAME->value,
                'value' => $firstName,
            ]);
        }

        return LeadPurchase::factory()->create([
            'lead_id' => $lead->getKey(),
            'buyer_tenant_id' => $this->buyer->getKey(),
            'purchased_by_user_id' => $purchaser?->getKey(),
        ]);
    }

    /**
     * Ein Mitglied des Kaeufer-Workspaces -- mit bestaetigter Rufnummer, sonst
     * haelt RequireVerifiedCallerId es auf der Bestaetigungsseite fest, bevor
     * es die zu pruefende Seite ueberhaupt erreicht.
     */
    private function memberOfBuyer(): User
    {
        $user = User::factory()->create();
        $this->buyer->users()->attach($user);

        CallerId::factory()->verified()->create([
            'tenant_id' => $this->buyer->getKey(),
            'user_id' => $user->getKey(),
        ]);

        return $user;
    }

    private function grantTeamLeads(User $user): void
    {
        $role = Role::query()
            ->withoutGlobalScopes()
            ->where('name', TenancyPermissionConstants::ROLE_ADMIN)
            ->where('is_tenant_role', true)
            ->sole();

        $user->tenants()->where('tenant_id', $this->buyer->getKey())->first()->pivot->assignRole($role);
    }
}
