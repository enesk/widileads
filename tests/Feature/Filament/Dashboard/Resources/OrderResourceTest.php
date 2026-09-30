<?php

namespace Tests\Feature\Filament\Dashboard\Resources;

use App\Constants\TenancyPermissionConstants;
use App\Constants\TenantType;
use App\Filament\Dashboard\Resources\Orders\OrderResource;
use App\Models\BuyerRegistration;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\FeatureTest;

class OrderResourceTest extends FeatureTest
{
    public function test_list(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant, [
            TenancyPermissionConstants::PERMISSION_VIEW_ORDERS,
        ]);

        $this->actingAs($user);

        // Umgeleitet wird nur ein Kaeufer mit freigeschaltetem Marktplatz --
        // ohne ihn faende er im Portal nichts vor.
        $tenant->update(['type' => TenantType::BUYER]);
        BuyerRegistration::factory()->approved()->create(['tenant_id' => $tenant->id]);
        $tenant->refresh();

        // Die Liste ist ins Portal umgezogen; das Panel leitet nur noch dorthin.
        $this->get(OrderResource::getUrl('index', [], true, 'dashboard', tenant: $tenant))
            ->assertRedirect(route('portal.orders', ['tenant' => $tenant->uuid]));

        $this->get(route('portal.orders', ['tenant' => $tenant->uuid]))->assertSuccessful();
    }

    public function test_list_fails_when_user_has_no_permission(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);

        $this->actingAs($user);
        $this->expectException(HttpException::class);

        // Ohne Marktplatzzugang wird gar nicht erst ins Portal geleitet --
        // es bleibt beim Panel und damit bei der Rechtepruefung von Filament.
        $this->get(OrderResource::getUrl('index', [], true, 'dashboard', tenant: $tenant));
    }
}
