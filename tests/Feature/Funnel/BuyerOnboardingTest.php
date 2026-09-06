<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\AuditAction;
use App\Constants\BuyerRegistrationStatus;
use App\Constants\TenancyPermissionConstants;
use App\Constants\TenantType;
use App\Models\AuditLog;
use App\Models\BuyerRegistration;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BuyerOnboardingService;
use App\Services\TenantTypeService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Tests\Feature\FeatureTest;

/**
 * FB-050: Ein Kaeufer entsteht durch Selbstregistrierung und kommt an keinen
 * Lead, bis der Plattform-Admin entschieden hat.
 *
 * Geprueft wird die Sperre, nicht das Formular: der Marktplatz selbst entsteht
 * erst mit FB-053, die Zugriffsregel muss aber schon jetzt tragen. Die Tests
 * haengen sich deshalb an die Middleware "marketplace.access" -- dieselbe, die
 * FB-053 vor seine Routen setzt -- und an das gleichnamige Gate.
 */
class BuyerOnboardingTest extends FeatureTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withExceptionHandling();
    }

    public function test_registration_creates_a_pending_buyer_tenant_with_an_av_timestamp(): void
    {
        $user = User::factory()->create();

        $registration = app(BuyerOnboardingService::class)->register($user, [
            'company_name' => 'Assekuranz Mustermann',
            'contact_name' => 'Anna Muster',
            'contact_email' => 'anna.muster@example.com',
            'contact_phone' => '+493012345678',
            'broker_register_number' => null,
            'vat_id' => 'DE123456789',
        ]);

        // Der Mandant ist ein Kaeufer und steht auf pending -- beides ist die
        // Voraussetzung dafuer, dass die Sperre unten ueberhaupt greifen kann.
        $tenant = $registration->tenant;

        $this->assertSame(TenantType::BUYER, $tenant->type);
        $this->assertSame(BuyerRegistrationStatus::PENDING, $registration->status);
        $this->assertFalse($registration->isApproved());

        // Der Nachweis der AV-Zustimmung: ohne ihn duerfen keine
        // personenbezogenen Lead-Daten uebermittelt werden.
        $this->assertNotNull($registration->av_accepted_at);

        // Entschieden hat noch niemand.
        $this->assertNull($registration->reviewed_at);
        $this->assertNull($registration->reviewed_by);

        // Der registrierende Benutzer haengt am Mandanten und traegt dort die
        // Kaeufer-Rolle aus FB-002 -- keine Parallelstruktur.
        $this->assertTrue($tenant->users()->whereKey($user->getKey())->exists());
        $this->assertTrue(
            $user->tenants()->where('tenant_id', $tenant->getKey())->first()
                ->pivot->hasRole(TenancyPermissionConstants::ROLE_BUYER),
        );
    }

    public function test_pending_buyer_cannot_reach_a_marketplace_route_but_an_approved_one_can(): void
    {
        Route::middleware(['web', 'marketplace.access'])
            ->get('/test/marktplatz', fn (): string => 'marktplatz')
            ->name('test.marketplace');

        $registration = BuyerRegistration::factory()->create();
        $user = $this->attachUserTo($registration->tenant);

        $this->actingAs($user);
        Filament::setTenant($registration->tenant);

        // In Pruefung: der Mandant ist ein Kaeufer, aber nicht freigeschaltet.
        $this->get('/test/marktplatz')->assertForbidden();

        app(BuyerOnboardingService::class)->approve(
            $registration,
            $this->createAdminUser(),
        );

        // Nach der Freischaltung derselbe Mandant, derselbe Benutzer, offener Weg.
        Filament::setTenant($registration->tenant->fresh());

        $this->actingAs($user)
            ->get('/test/marktplatz')
            ->assertSuccessful()
            ->assertSee('marktplatz');
    }

    public function test_marketplace_access_is_denied_to_operators_and_to_rejected_buyers(): void
    {
        $service = app(TenantTypeService::class);
        $user = User::factory()->create();

        // Ein Betreiber-Mandant hat nie Marktplatzzugriff -- unabhaengig von
        // jeder Freischaltung (FB-002).
        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $this->assertFalse($service->canAccessMarketplace($operator));

        // Ein Kaeufer ohne Registrierungssatz ebenfalls nicht: der Typ allein
        // genuegt seit FB-050 nicht.
        $withoutRegistration = Tenant::factory()->create(['type' => TenantType::BUYER]);
        $this->assertFalse($service->canAccessMarketplace($withoutRegistration->fresh()));

        $pending = BuyerRegistration::factory()->create();
        $this->assertFalse($service->canAccessMarketplace($pending->tenant->fresh()));

        $rejected = BuyerRegistration::factory()->rejected()->create();
        $this->assertFalse($service->canAccessMarketplace($rejected->tenant->fresh()));

        $approved = BuyerRegistration::factory()->approved()->create();
        $this->assertTrue($service->canAccessMarketplace($approved->tenant->fresh()));

        // Das Gate beantwortet dieselbe Frage wie der Dienst - sonst wirkte die
        // Sperre an einer Stelle und fehlte an der anderen.
        $this->actingAs($user);

        $this->assertFalse($user->can('marketplace.access', $pending->tenant->fresh()));
        $this->assertTrue($user->can('marketplace.access', $approved->tenant->fresh()));
    }

    public function test_approval_and_rejection_are_written_to_the_audit_log(): void
    {
        $admin = $this->createAdminUser();
        $service = app(BuyerOnboardingService::class);

        $approved = BuyerRegistration::factory()->create();
        $service->approve($approved, $admin);

        $entry = AuditLog::query()->where('action', AuditAction::BUYER_APPROVED)->sole();

        $this->assertSame($approved->tenant_id, $entry->tenant_id);
        $this->assertSame($admin->getKey(), $entry->user_id);
        $this->assertSame((string) $approved->getKey(), $entry->subject_id);
        $this->assertSame(BuyerRegistrationStatus::PENDING->value, $entry->payload['previous_status']);

        $rejected = BuyerRegistration::factory()->create();
        $service->reject($rejected, $admin, 'Keine gueltige Gewerbeanmeldung vorgelegt.');

        $entry = AuditLog::query()->where('action', AuditAction::BUYER_REJECTED)->sole();

        $this->assertSame('Keine gueltige Gewerbeanmeldung vorgelegt.', $entry->payload['reason']);
        $this->assertSame(
            BuyerRegistrationStatus::REJECTED,
            $rejected->fresh()->status,
        );
    }

    private function attachUserTo(Tenant $tenant): User
    {
        $user = User::factory()->create();

        $tenant->users()->attach($user);

        return $user;
    }
}
