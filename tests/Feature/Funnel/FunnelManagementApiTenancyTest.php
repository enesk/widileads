<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\TenantApiAbility;
use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\Tenant;
use App\Services\TenantApiTokenService;
use Tests\Feature\FeatureTest;

/**
 * FB-030b: Mandantentrennung der Funnel-Verwaltung.
 *
 * Der einzige Test dieses Tickets -- und der einzige, der es sein muss: Ein
 * Token darf unter keinen Umstaenden an die Funnels eines anderen Workspaces
 * kommen, weder lesend noch schreibend. Ein Fehler hier waere kein Fehlverhalten
 * der Oberflaeche, sondern ein Datenleck zwischen Kunden.
 */
class FunnelManagementApiTenancyTest extends FeatureTest
{
    public function test_a_token_never_reaches_funnels_of_another_workspace(): void
    {
        $this->withExceptionHandling();

        $own = Tenant::factory()->create();
        $foreign = Tenant::factory()->create();

        $ownFunnel = Funnel::factory()->forTenant($own)->create(['name' => 'Eigener Funnel']);
        $foreignFunnel = Funnel::factory()->forTenant($foreign)->create(['name' => 'Fremder Funnel']);
        $foreignStep = FunnelStep::factory()->create(['funnel_id' => $foreignFunnel->id]);

        $headers = $this->authorizationFor($own);

        // ZUERST der fremde Funnel, bevor irgendein anderer Aufruf lief: Der
        // Mandantenkontext lebt im Container weiter, ein vorheriger Request
        // wuerde ihn also setzen und das Ergebnis faelschen. In Produktion ist
        // jeder Request frisch -- genau dieser Fall muss geprueft werden.
        $this->getJson('/api/v1/funnels/'.$foreignFunnel->public_token, $headers)
            ->assertNotFound()
            ->assertJsonPath('type', '/problems/not-found');

        // Die Liste zeigt nur die eigenen Funnels.
        $list = $this->getJson('/api/v1/funnels', $headers)->assertOk();

        $list->assertJsonPath('data.0.public_token', $ownFunnel->public_token);
        $list->assertJsonCount(1, 'data');
        $list->assertDontSee('Fremder Funnel');

        // Ein fremder Funnel ist nicht "verboten", sondern nicht vorhanden:
        // Sonst liesse sich an der Antwort ablesen, dass es ihn gibt.
        $this->patchJson('/api/v1/funnels/'.$foreignFunnel->public_token, ['name' => 'Uebernommen'], $headers)
            ->assertNotFound();

        $this->deleteJson('/api/v1/funnels/'.$foreignFunnel->public_token, [], $headers)
            ->assertNotFound();

        $this->putJson(
            '/api/v1/funnels/'.$foreignFunnel->public_token.'/structure',
            ['steps' => [], 'results' => []],
            $headers,
        )->assertNotFound();

        // Auch die Bausteine eines fremden Funnels bleiben unerreichbar --
        // selbst wenn ihre Kennung geraten wird.
        $this->getJson('/api/v1/funnels/'.$foreignFunnel->public_token.'/steps/'.$foreignStep->id, $headers)
            ->assertNotFound();

        // Und ein fremder Schritt unter dem eigenen Funnel ebenfalls nicht.
        $this->getJson('/api/v1/funnels/'.$ownFunnel->public_token.'/steps/'.$foreignStep->id, $headers)
            ->assertNotFound();

        $this->assertSame('Fremder Funnel', $foreignFunnel->refresh()->name);
        $this->assertDatabaseHas('funnels', ['id' => $foreignFunnel->id]);
    }

    public function test_a_read_only_token_cannot_write(): void
    {
        $this->withExceptionHandling();

        $tenant = Tenant::factory()->create();
        $funnel = Funnel::factory()->forTenant($tenant)->create();

        $token = app(TenantApiTokenService::class)
            ->create($tenant, 'Nur lesen', [TenantApiAbility::FUNNELS_READ->value]);

        $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];

        $this->getJson('/api/v1/funnels/'.$funnel->public_token, $headers)->assertOk();

        $this->patchJson('/api/v1/funnels/'.$funnel->public_token, ['name' => 'Neu'], $headers)
            ->assertForbidden()
            ->assertJsonPath('type', '/problems/insufficient-ability');
    }

    /**
     * @return array<string, string>
     */
    private function authorizationFor(Tenant $tenant): array
    {
        $token = app(TenantApiTokenService::class)
            ->create($tenant, 'Testzugang', TenantApiAbility::values());

        return ['Authorization' => 'Bearer '.$token->plainTextToken];
    }
}
