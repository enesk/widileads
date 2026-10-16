<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Constants\CallerIdStatus;
use App\Models\BuyerRegistration;
use App\Models\CallerId;
use App\Models\Tenant;
use App\Models\User;
use Tests\Feature\FeatureTest;

/**
 * Die Rufnummernanzeige bewacht das Portal nicht mehr (Ticket #49).
 *
 * Vorher stand hier die Sperre RequireVerifiedCallerId: Sie hielt jeden
 * Kaeufer ohne nutzbare `caller_ids`-Zeile auf der Bestaetigungsseite fest.
 *
 * Geprueft wird deshalb das Gegenstueck: Die Portalseiten sind ohne nutzbare
 * Nummer erreichbar. Dass der Anruf ohne Nummer weiterhin verweigert wird,
 * entscheidet der CallService und steht in dessen Tests.
 *
 * Die beiden Faelle zur Erneuerung einer unbrauchbaren Zeile durch den
 * Verify-Nachweis sind mit Ticket #97 entfallen: Es gibt keinen zweiten
 * Nachweis mehr, der eine Zeile heben koennte -- bestaetigt wird genau einmal,
 * und zwar durch den Bestaetigungsanruf (CallerIdService, siehe CallerIdTest).
 */
class PortalWithoutVerifiedCallerIdTest extends FeatureTest
{
    private const NUMBER = '+493012345678';

    private const OTHER_NUMBER = '+493098765432';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withExceptionHandling();
    }

    public function test_the_portal_stays_reachable_with_an_expired_caller_id(): void
    {
        [$tenant, $user] = $this->approvedBuyer();

        $this->expiredCallerId($tenant, $user, self::NUMBER);

        $this->actingAs($user)
            ->get(route('portal.overview', ['tenant' => $tenant->uuid]))
            ->assertSuccessful();
    }

    public function test_the_caller_id_page_stays_reachable(): void
    {
        [$tenant, $user] = $this->approvedBuyer();

        $this->expiredCallerId($tenant, $user, self::NUMBER);

        $this->actingAs($user)
            ->get(route('portal.caller-id', ['tenant' => $tenant->uuid]))
            ->assertSuccessful();
    }

    private function expiredCallerId(Tenant $tenant, User $user, string $number): CallerId
    {
        return CallerId::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'phone_number' => $number,
            'status' => CallerIdStatus::EXPIRED,
            'expires_at' => now()->subHour(),
        ]);
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
