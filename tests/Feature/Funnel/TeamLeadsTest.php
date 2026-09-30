<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\FunnelFieldKey;
use App\Constants\TenancyPermissionConstants;
use App\Livewire\Portal\TeamLeads;
use App\Models\BuyerRegistration;
use App\Models\CallerId;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\LeadPurchase;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\FeatureTest;

/**
 * Ticket #3: Die Portalseite "Team Leads".
 *
 * Geprueft wird das, woran die Seite haengt und nicht ihre Gestaltung: das
 * Recht, die Mandantentrennung und die Maskierung. Die Maskierung ist der
 * eigentliche Punkt -- die Seite zeigt Kaeufe von Kollegen, und Rufnummer und
 * E-Mail duerfen dabei auch fuer einen Admin nicht durchkommen. Weder
 * sichtbar noch im Livewire-Zustand.
 */
class TeamLeadsTest extends FeatureTest
{
    private Tenant $buyer;

    private User $admin;

    private User $colleague;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = BuyerRegistration::factory()->approved()->create()->tenant->fresh();
        $this->admin = $this->memberOfBuyer();
        $this->colleague = $this->memberOfBuyer();

        $this->grantTeamLeads($this->admin);
    }

    public function test_ohne_das_recht_antwortet_die_seite_mit_403(): void
    {
        $this->purchase($this->colleague);

        $this->actingAs($this->colleague);
        Filament::setTenant($this->buyer, isQuiet: true);

        $this->expectException(HttpException::class);

        Livewire::test(TeamLeads::class);
    }

    public function test_die_seite_zeigt_die_kaeufe_des_ganzen_mandanten(): void
    {
        $this->purchase($this->admin, 'Alfacheck');
        $this->purchase($this->colleague, 'Betacheck');
        $this->purchase(null, 'Gammacheck');

        $this->actingAs($this->admin);
        Filament::setTenant($this->buyer, isQuiet: true);

        Livewire::test(TeamLeads::class)
            ->assertSee('Alfacheck')
            ->assertSee('Betacheck')
            // Der Autokauf traegt keinen Menschen und steht als "Automatisch".
            ->assertSee('Gammacheck')
            ->assertSee('Automatisch');
    }

    public function test_die_seite_ist_ueber_ihre_adresse_erreichbar(): void
    {
        $this->purchase($this->admin, 'Alfacheck');

        $this->actingAs($this->admin);

        $this->get(route('portal.team-leads', ['tenant' => $this->buyer->uuid]))
            ->assertOk()
            ->assertSee('Team Leads')
            ->assertSee('Alfacheck');
    }

    public function test_ein_kauf_eines_fremden_mandanten_steht_nicht_in_der_liste(): void
    {
        $this->purchase($this->admin, 'Alfacheck');

        $stranger = BuyerRegistration::factory()->approved()->create()->tenant->fresh();
        $strangerUser = User::factory()->create();
        $stranger->users()->attach($strangerUser);

        $foreignLead = Lead::factory()->create();
        LeadAnswer::query()->create([
            'lead_id' => $foreignLead->getKey(),
            'field_key' => FunnelFieldKey::VORNAME->value,
            'value' => 'Fremdcheck',
        ]);
        LeadPurchase::factory()->create([
            'lead_id' => $foreignLead->getKey(),
            'buyer_tenant_id' => $stranger->getKey(),
            'purchased_by_user_id' => $strangerUser->getKey(),
        ]);

        $this->actingAs($this->admin);
        Filament::setTenant($this->buyer, isQuiet: true);

        Livewire::test(TeamLeads::class)
            ->assertSee('Alfacheck')
            ->assertDontSee('Fremdcheck');
    }

    public function test_rufnummer_und_email_bleiben_auch_fuer_den_admin_verdeckt(): void
    {
        $lead = Lead::factory()->create([
            'phone_e164' => '+491711234567',
            'email_normalized' => 'mara.lindqvist@example.com',
        ]);

        LeadPurchase::factory()->create([
            'lead_id' => $lead->getKey(),
            'buyer_tenant_id' => $this->buyer->getKey(),
            'purchased_by_user_id' => $this->colleague->getKey(),
        ]);

        $this->actingAs($this->admin);
        Filament::setTenant($this->buyer, isQuiet: true);

        $component = Livewire::test(TeamLeads::class);

        // Die verkuerzte Fassung steht da -- sonst wuerde dieser Test auch
        // dann gruen, wenn die Zeile gar keine Nummer zeigt.
        $component->assertSee('***** 67');

        // Weder in der Ausgabe ...
        $component
            ->assertDontSee('+491711234567')
            ->assertDontSee('1234567')
            ->assertDontSee('mara.lindqvist@example.com');

        // ... noch im Zustand, der mit jeder Folgeanfrage durch den Browser
        // laeuft. Eine im Blade weggelassene Nummer stuende hier trotzdem.
        $state = json_encode($component->getData()) ?: '';

        $this->assertStringNotContainsString('1234567', $state);
        $this->assertStringNotContainsString('mara.lindqvist', $state);
    }

    public function test_der_export_enthaelt_keine_rufnummer_und_keine_email(): void
    {
        $lead = Lead::factory()->create([
            'phone_e164' => '+491711234567',
            'email_normalized' => 'mara.lindqvist@example.com',
        ]);

        LeadPurchase::factory()->create([
            'lead_id' => $lead->getKey(),
            'buyer_tenant_id' => $this->buyer->getKey(),
            'purchased_by_user_id' => $this->colleague->getKey(),
        ]);

        $this->actingAs($this->admin);
        Filament::setTenant($this->buyer, isQuiet: true);

        $response = Livewire::test(TeamLeads::class)->call('exportCsv')->effects['download'] ?? null;

        $this->assertNotNull($response, 'Der Export hat keine Datei geliefert.');

        $csv = base64_decode((string) $response['content'], true) ?: '';

        $this->assertStringNotContainsString('1234567', $csv);
        $this->assertStringNotContainsString('mara.lindqvist', $csv);
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
     * haelt RequireVerifiedCallerId es auf der Bestaetigungsseite fest.
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
