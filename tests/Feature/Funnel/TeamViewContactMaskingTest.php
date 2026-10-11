<?php

namespace Tests\Feature\Funnel;

use App\Constants\LeadContactStatus;
use App\Constants\LeadState;
use App\Constants\TenancyPermissionConstants;
use App\Models\BuyerRegistration;
use App\Models\CallerId;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\LeadPurchase;
use App\Models\Role;
use App\Models\User;
use Tests\Feature\FeatureTest;

/**
 * Die Kontaktdaten gehoeren dem Kaeufer, nicht dem Workspace.
 *
 * Wer einen Kauf nur lesen darf ("Team Leads", Recht `view team leads`),
 * bekommt auf der Detailseite die Teamsicht -- auch als Admin des Workspace.
 * Geprueft wird der schlimmste Fall: ein abgerechneter Lead, dessen Nummer
 * dem Kaeufer bereits freigegeben wurde.
 */
class TeamViewContactMaskingTest extends FeatureTest
{
    public function test_der_workspace_admin_sieht_die_nummer_eines_kollegen_nicht(): void
    {
        $buyer = BuyerRegistration::factory()->approved()->create()->tenant->fresh();

        $admin = User::factory()->create();
        $buyer->users()->attach($admin);
        CallerId::factory()->verified()->create(['tenant_id' => $buyer->id, 'user_id' => $admin->id]);
        $role = Role::query()->withoutGlobalScopes()
            ->where('name', TenancyPermissionConstants::ROLE_ADMIN)->where('is_tenant_role', true)->sole();
        $admin->tenants()->where('tenant_id', $buyer->id)->first()->pivot->assignRole($role);

        $colleague = User::factory()->create();
        $buyer->users()->attach($colleague);

        // Lead mit freigegebener Nummer: abgerechnet.
        $lead = Lead::factory()->create([
            'lead_state' => LeadState::VERKAUFT,
            'contact_status' => LeadContactStatus::BILLABLE,
            'phone_e164' => '+4915112345678',
        ]);
        LeadAnswer::query()->create(['lead_id' => $lead->id, 'field_key' => 'telefon', 'value' => '+4915112345678']);

        $purchase = LeadPurchase::factory()->create([
            'lead_id' => $lead->id,
            'buyer_tenant_id' => $buyer->id,
            'purchased_by_user_id' => $colleague->id,
            'purchased_at' => now(),
        ]);

        $this->actingAs($admin);

        $url = route('portal.leads.show', ['tenant' => $buyer->uuid, 'purchase' => $purchase->id]);

        $this->get($url)
            ->assertOk()
            ->assertDontSee('+4915112345678')
            ->assertDontSee('15112345678');
    }
}
