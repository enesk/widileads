<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Constants\TenantType;
use App\Models\BuyerRegistration;
use App\Models\CallerId;
use App\Models\Tenant;
use App\Models\User;
use Tests\Feature\FeatureTest;

/**
 * Ein Kaeufer ohne bestaetigte Rufnummer kommt im Portal nur auf die
 * Bestaetigungsseite.
 *
 * Geprueft wird die Sperre selbst und ihre beiden Ausnahmen: die
 * Bestaetigungsseite, die sonst auf sich selbst umleitete, und der
 * Betreiber-Mandant, fuer den es nichts zu bestaetigen gibt.
 */
class RequireVerifiedCallerIdTest extends FeatureTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withExceptionHandling();
    }

    public function test_buyer_without_a_verified_number_is_sent_to_the_verification_page(): void
    {
        [$tenant, $user] = $this->approvedBuyer();

        $this->actingAs($user)
            ->get(route('portal.marketplace', ['tenant' => $tenant->uuid]))
            ->assertRedirect(route('portal.caller-id', ['tenant' => $tenant->uuid]));
    }

    public function test_the_verification_page_itself_stays_reachable(): void
    {
        [$tenant, $user] = $this->approvedBuyer();

        $this->actingAs($user)
            ->get(route('portal.caller-id', ['tenant' => $tenant->uuid]))
            ->assertSuccessful();
    }

    public function test_a_verified_number_opens_the_portal(): void
    {
        [$tenant, $user] = $this->approvedBuyer();

        CallerId::factory()->verified()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->actingAs($user)
            ->get(route('portal.marketplace', ['tenant' => $tenant->uuid]))
            ->assertSuccessful();
    }

    /**
     * Ein Workspace vom Typ `operator` ist nicht dasselbe wie ein Verkaeufer:
     * Wer sich ueber /register anmeldet, bekommt genau diesen Typ, auch als
     * Kaeufer. Die Sperre darf sich deshalb nicht am Typ festmachen.
     */
    public function test_a_workspace_from_the_plain_registration_is_locked_too(): void
    {
        $tenant = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $user = User::factory()->create();
        $tenant->users()->attach($user);

        $this->actingAs($user)
            ->get(route('portal.overview', ['tenant' => $tenant->uuid]))
            ->assertRedirect(route('portal.caller-id', ['tenant' => $tenant->uuid]));
    }

    public function test_the_lock_can_be_switched_off(): void
    {
        config()->set('funnel.call.caller_id.required_for_portal', false);

        [$tenant, $user] = $this->approvedBuyer();

        $this->actingAs($user)
            ->get(route('portal.marketplace', ['tenant' => $tenant->uuid]))
            ->assertSuccessful();
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function approvedBuyer(): array
    {
        $registration = BuyerRegistration::factory()->approved()->create();
        $tenant = $registration->tenant;

        $user = User::factory()->create();
        $tenant->users()->attach($user);

        return [$tenant->fresh(), $user];
    }
}
