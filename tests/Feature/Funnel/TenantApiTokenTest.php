<?php

namespace Tests\Feature\Funnel;

use App\Constants\TenantApiAbility;
use App\Models\Tenant;
use App\Services\TenantApiTokenService;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Feature\FeatureTest;

/**
 * FB-006: API-Tokens gehoeren einem Tenant. Ein Token darf ausschliesslich die
 * Daten seines eigenen Tenants erreichen.
 */
class TenantApiTokenTest extends FeatureTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withExceptionHandling();
    }

    public function test_token_resolves_its_own_tenant(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Tierarztportal']);

        $response = $this->getJson('/api/v1/me', $this->authorizationFor($tenant))
            ->assertSuccessful();

        $response->assertJsonPath('data.uuid', $tenant->uuid);
        $response->assertJsonPath('data.name', 'Tierarztportal');
    }

    public function test_token_of_one_tenant_never_reaches_another_tenant(): void
    {
        $own = Tenant::factory()->create(['name' => 'Eigener Workspace']);
        $foreign = Tenant::factory()->create(['name' => 'Fremder Workspace']);

        $response = $this->getJson('/api/v1/me', $this->authorizationFor($own))
            ->assertSuccessful();

        $response->assertJsonPath('data.uuid', $own->uuid);
        $response->assertJsonMissing(['uuid' => $foreign->uuid]);
        $response->assertDontSee('Fremder Workspace');
    }

    public function test_revoked_token_loses_access_immediately(): void
    {
        $tenant = Tenant::factory()->create();
        $headers = $this->authorizationFor($tenant);

        $this->getJson('/api/v1/me', $headers)->assertSuccessful();

        $this->assertTrue(
            app(TenantApiTokenService::class)->revoke($tenant, $tenant->tokens()->firstOrFail()->id),
        );
        $this->assertSame(0, $tenant->tokens()->count());

        // Der Sanctum-Guard merkt sich den aufgeloesten Tokentraeger fuer die
        // Dauer der Anwendungsinstanz. Im Test teilen sich beide Aufrufe diese
        // Instanz, im Betrieb nicht - deshalb hier den Guard zuruecksetzen.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/me', $headers)->assertUnauthorized();
    }

    public function test_api_v1_requires_a_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer erfunden'])->assertUnauthorized();
    }

    public function test_a_user_token_is_rejected_because_it_belongs_to_no_tenant(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('persoenlich', TenantApiAbility::values())->plainTextToken;

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$token])->assertForbidden();
    }

    public function test_token_records_its_last_use(): void
    {
        $tenant = Tenant::factory()->create();
        $headers = $this->authorizationFor($tenant);

        $this->assertNull($tenant->tokens()->firstOrFail()->last_used_at);

        $this->getJson('/api/v1/me', $headers)->assertSuccessful();

        $this->assertNotNull($tenant->tokens()->firstOrFail()->fresh()->last_used_at);
    }

    public function test_only_known_abilities_are_stored(): void
    {
        $tenant = Tenant::factory()->create();

        app(TenantApiTokenService::class)->create($tenant, 'CRM', [
            TenantApiAbility::LEADS_READ->value,
            TenantApiAbility::LEADS_READ->value,
            'leads:delete-everything',
            '*',
        ]);

        $this->assertSame(
            [TenantApiAbility::LEADS_READ->value],
            $tenant->tokens()->firstOrFail()->abilities,
        );
    }

    public function test_response_reports_the_abilities_of_the_used_token(): void
    {
        $tenant = Tenant::factory()->create();

        $token = app(TenantApiTokenService::class)->create($tenant, 'Einbettung', [
            TenantApiAbility::FUNNELS_READ->value,
            TenantApiAbility::LEADS_READ->value,
        ]);

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$token->plainTextToken])
            ->assertSuccessful()
            ->assertJsonPath('data.abilities', [
                TenantApiAbility::FUNNELS_READ->value,
                TenantApiAbility::LEADS_READ->value,
            ]);
    }

    public function test_a_tenant_cannot_revoke_a_token_of_another_tenant(): void
    {
        $own = Tenant::factory()->create();
        $foreign = Tenant::factory()->create();

        $service = app(TenantApiTokenService::class);
        $service->create($own, 'eigenes', TenantApiAbility::values());
        $foreignToken = $service->create($foreign, 'fremdes', TenantApiAbility::values());

        $foreignTokenId = $foreign->tokens()->firstOrFail()->id;

        $this->assertFalse($service->revoke($own, $foreignTokenId));
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreignTokenId]);

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$foreignToken->plainTextToken])
            ->assertSuccessful();
    }

    public function test_tokens_are_stored_hashed_only(): void
    {
        $tenant = Tenant::factory()->create();

        $token = app(TenantApiTokenService::class)->create($tenant, 'CRM', TenantApiAbility::values());

        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $token->plainTextToken]);
        $this->assertNotNull(PersonalAccessToken::findToken($token->plainTextToken));
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
