<?php

namespace Tests\Feature\Funnel;

use App\Constants\TenancyPermissionConstants;
use App\Constants\TenantType;
use App\Models\Tenant;
use App\Services\TenantTypeService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\Feature\FeatureTest;

/**
 * FB-002: Betreiber-Tenants verwalten Funnels, Kaeufer-Tenants nutzen den
 * Marktplatz. Der Tenant-Typ ist die einzige Quelle dieser Entscheidung.
 */
class TenantTypeTest extends FeatureTest
{
    /**
     * Die Funnel-Verwaltung und der Marktplatz entstehen erst in FB-E1 bzw.
     * FB-E5. Damit die Zugriffsregel schon jetzt belegt ist, laufen die Tests
     * gegen Routen, die dieselbe Middleware und dieselben Gates verwenden.
     *
     * @var list<string>
     */
    private const OPERATOR_ROUTES = [
        '/test/funnels',
        '/test/funnels/create',
        '/test/funnels/settings',
    ];

    /**
     * @var list<string>
     */
    private const BUYER_ROUTES = [
        '/test/marketplace',
        '/test/marketplace/leads',
        '/test/marketplace/credits',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::OPERATOR_ROUTES as $uri) {
            Route::get($uri, fn () => response('ok'))
                ->middleware(['web', 'tenant.type:operator']);
        }

        foreach (self::BUYER_ROUTES as $uri) {
            Route::get($uri, fn () => response('ok'))
                ->middleware(['web', 'tenant.type:buyer']);
        }

        $this->withExceptionHandling();
    }

    public function test_operator_tenant_reaches_funnel_management(): void
    {
        $this->actingAsUserOfTenant(Tenant::factory()->operator()->create());

        foreach (self::OPERATOR_ROUTES as $uri) {
            $this->get($uri)->assertSuccessful();
        }
    }

    public function test_buyer_tenant_is_locked_out_of_funnel_management(): void
    {
        $this->actingAsUserOfTenant(Tenant::factory()->buyer()->create());

        foreach (self::OPERATOR_ROUTES as $uri) {
            $this->get($uri)->assertForbidden();
        }
    }

    public function test_buyer_tenant_reaches_the_marketplace(): void
    {
        $this->actingAsUserOfTenant(Tenant::factory()->buyer()->create());

        foreach (self::BUYER_ROUTES as $uri) {
            $this->get($uri)->assertSuccessful();
        }
    }

    public function test_operator_tenant_is_locked_out_of_the_marketplace(): void
    {
        $this->actingAsUserOfTenant(Tenant::factory()->operator()->create());

        foreach (self::BUYER_ROUTES as $uri) {
            $this->get($uri)->assertForbidden();
        }
    }

    public function test_without_an_active_tenant_every_tenant_typed_route_is_forbidden(): void
    {
        $this->actingAs($this->createUser());

        Filament::setTenant(null, isQuiet: true);

        foreach ([...self::OPERATOR_ROUTES, ...self::BUYER_ROUTES] as $uri) {
            $this->get($uri)->assertForbidden();
        }
    }

    public function test_tenants_are_operators_by_default(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertSame(TenantType::OPERATOR, $tenant->type);
        $this->assertTrue($tenant->isOperator());
        $this->assertFalse($tenant->isBuyer());
    }

    public function test_gates_follow_the_tenant_type(): void
    {
        $user = $this->createUser();
        $operator = Tenant::factory()->operator()->create();
        $buyer = Tenant::factory()->buyer()->create();

        $this->assertTrue(Gate::forUser($user)->allows('funnels.manage', $operator));
        $this->assertFalse(Gate::forUser($user)->allows('marketplace.access', $operator));

        $this->assertTrue(Gate::forUser($user)->allows('marketplace.access', $buyer));
        $this->assertFalse(Gate::forUser($user)->allows('funnels.manage', $buyer));
    }

    public function test_service_reports_capabilities_per_type(): void
    {
        $service = app(TenantTypeService::class);
        $operator = Tenant::factory()->operator()->create();
        $buyer = Tenant::factory()->buyer()->create();

        $this->assertTrue($service->isOperator($operator));
        $this->assertTrue($service->canManageFunnels($operator));
        $this->assertFalse($service->canAccessMarketplace($operator));

        $this->assertTrue($service->isBuyer($buyer));
        $this->assertTrue($service->canAccessMarketplace($buyer));
        $this->assertFalse($service->canManageFunnels($buyer));

        $this->assertFalse($service->canManageFunnels(null));
        $this->assertFalse($service->canAccessMarketplace(null));
    }

    public function test_buyer_role_is_seeded_as_a_tenant_role(): void
    {
        $this->assertDatabaseHas('roles', [
            'name' => TenancyPermissionConstants::ROLE_BUYER,
            'is_tenant_role' => true,
        ]);
    }

    private function actingAsUserOfTenant(Tenant $tenant): void
    {
        $this->actingAs($this->createUser($tenant));

        Filament::setTenant($tenant, isQuiet: true);
    }
}
