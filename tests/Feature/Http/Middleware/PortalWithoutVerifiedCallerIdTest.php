<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Constants\CallerIdStatus;
use App\Constants\PhoneVerificationChannel;
use App\Events\Phone\PhoneVerified;
use App\Models\BuyerRegistration;
use App\Models\CallerId;
use App\Models\Tenant;
use App\Models\User;
use Tests\Feature\FeatureTest;

/**
 * Die Rufnummernanzeige bewacht das Portal nicht mehr (Ticket #49).
 *
 * Vorher stand hier die Sperre RequireVerifiedCallerId: Sie hielt jeden
 * Kaeufer ohne nutzbare `caller_ids`-Zeile auf der Bestaetigungsseite fest --
 * auch den, dessen Nummer Twilio Verify Minuten vorher bestaetigt hatte. Auf
 * einem Trial-Konto war das eine Sackgasse, weil Bestaetigungsanrufe dort
 * gesperrt sind.
 *
 * Geprueft wird deshalb das Gegenstueck: Die Portalseiten sind ohne nutzbare
 * Nummer erreichbar, und die Telefonbestaetigung hebt eine unbrauchbare Zeile
 * mit derselben Nummer selbst wieder auf `verified`. Dass der Anruf ohne
 * Nummer weiterhin verweigert wird, entscheidet der CallService und steht in
 * dessen Tests.
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

    /**
     * Der frische Verify-Nachweis sagt ueber diese Nummer dasselbe, was der
     * Bestaetigungsanruf sagen wuerde -- also wird die Zeile gehoben statt ein
     * zweiter Nachweis verlangt.
     */
    public function test_phone_verification_renews_an_expired_row_with_the_same_number(): void
    {
        [$tenant, $user] = $this->approvedBuyer();

        $callerId = $this->expiredCallerId($tenant, $user, self::NUMBER);

        $user->forceFill([
            'phone_e164' => self::NUMBER,
            'phone_number_verified_at' => now(),
        ])->save();

        event(new PhoneVerified($user, PhoneVerificationChannel::SMS));

        $callerId->refresh();

        $this->assertSame(CallerIdStatus::VERIFIED, $callerId->status);
        $this->assertNotNull($callerId->verified_at);
        $this->assertNull($callerId->expires_at);
        $this->assertNull($callerId->validation_code);
        $this->assertNull($callerId->validation_sid);
        $this->assertSame(self::NUMBER, $callerId->phone_number);

        // Eine zweite Zeile waere ein zweiter Stand fuer denselben Nutzer.
        $this->assertSame(1, CallerId::query()->withoutGlobalScopes()
            ->where('user_id', $user->getKey())->count());
    }

    /**
     * Eine andere Nummer in der Zeile ist eine bewusste Entscheidung von Nutzer
     * oder Support -- etwa eine Firmennummer, deren Bestaetigungsanruf
     * scheiterte. Der Nachweis gilt nur fuer die eigene Nummer.
     */
    public function test_an_expired_row_with_another_number_stays_untouched(): void
    {
        [$tenant, $user] = $this->approvedBuyer();

        $callerId = $this->expiredCallerId($tenant, $user, self::OTHER_NUMBER);

        $user->forceFill([
            'phone_e164' => self::NUMBER,
            'phone_number_verified_at' => now(),
        ])->save();

        event(new PhoneVerified($user, PhoneVerificationChannel::SMS));

        $callerId->refresh();

        $this->assertSame(CallerIdStatus::EXPIRED, $callerId->status);
        $this->assertSame(self::OTHER_NUMBER, $callerId->phone_number);
        $this->assertSame(1, CallerId::query()->withoutGlobalScopes()
            ->where('user_id', $user->getKey())->count());
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
