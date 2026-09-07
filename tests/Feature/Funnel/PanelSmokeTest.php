<?php

namespace Tests\Feature\Funnel;

use App\Constants\TenancyPermissionConstants;
use App\Constants\TenantType;
use App\Models\BuyerRegistration;
use Tests\Feature\FeatureTest;

/**
 * Belegt, dass die beiden Panels ueberhaupt laden (FB-028e).
 *
 * Diese drei Tests sind die einzigen, die eine Oberflaeche rendern, und sie
 * pruefen bewusst nichts ausser dem Statuscode. Der Grund steht in Abschnitt 8
 * des Leitfadens: Erreichbarkeit ist kein Aussehen. Ein Fehler in der
 * Seitenleiste trifft *jede* Seite des Panels gleichzeitig, und er faellt sonst
 * niemandem auf - die Seitenleiste wurde in der gesamten Suite nie gerendert,
 * ein 500er dort waere mit gruenem "composer check" ausgeliefert worden.
 *
 * Zweimal ist genau das schon passiert:
 *
 * - __('Leads') als Gruppenname lieferte auf macOS das Array aus
 *   lang/de/leads.php statt eines Strings, und getNavigationGroup(): ?string
 *   warf einen TypeError.
 * - Ein Icon an einer Navigationsgruppe, deren Eintraege ebenfalls Icons haben,
 *   laesst Filament mit einer Exception abbrechen.
 *
 * Beide Male war die Ursache in Minuten behoben; gefunden hat sie ein Mensch,
 * der die Seite aufgerufen hat. Genau diese Luecke schliessen die drei Tests.
 *
 * Nicht erweitern: keine Bezeichnungen, keine Reihenfolge, keine Struktur. Was
 * in der Navigation steht und wie es heisst, bleibt testfrei.
 */
class PanelSmokeTest extends FeatureTest
{
    public function test_admin_panel_laedt(): void
    {
        $this->actingAs($this->createAdminUser());

        $this->get('/admin')->assertOk();
    }

    public function test_dashboard_eines_betreibers_laedt(): void
    {
        $tenant = $this->createTenant();
        $tenant->update(['type' => TenantType::OPERATOR]);

        $this->actingAs($this->createUser($tenant, [
            TenancyPermissionConstants::PERMISSION_MANAGE_FUNNELS,
            TenancyPermissionConstants::PERMISSION_MANAGE_API_TOKENS,
        ]));

        $this->get('/dashboard/'.$tenant->uuid)->assertOk();
    }

    public function test_dashboard_eines_kaeufers_laedt(): void
    {
        $tenant = $this->createTenant();
        $tenant->update(['type' => TenantType::BUYER]);

        // Ohne freigeschaltete Registrierung blieben Marktplatz und
        // Kaufkriterien aus der Navigation - dann wuerde der Test genau die
        // Eintraege nicht rendern, um die es hier geht (FB-050).
        BuyerRegistration::factory()->approved()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($this->createUser($tenant, [
            TenancyPermissionConstants::PERMISSION_MANAGE_API_TOKENS,
        ]));

        $this->get('/dashboard/'.$tenant->refresh()->uuid)->assertOk();
    }
}
