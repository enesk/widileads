<?php

namespace Tests\Feature\Funnel;

use App\Constants\AuditAction;
use App\Constants\TenantApiAbility;
use App\Exceptions\TenantApiTokenLimitReachedException;
use App\Models\AuditLog;
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

    public function test_a_token_without_the_ability_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();

        $token = app(TenantApiTokenService::class)
            ->create($tenant, 'Nur Funnels', [TenantApiAbility::FUNNELS_READ->value]);

        $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];

        // /me steht jedem gueltigen Token offen ...
        $this->getJson('/api/v1/me', $headers)->assertSuccessful();

        // ... die durch leads:read geschuetzte Route jedoch nicht.
        $this->getJson('/api/v1/ping/leads', $headers)->assertForbidden();
    }

    public function test_a_token_with_the_ability_is_accepted(): void
    {
        $tenant = Tenant::factory()->create();

        $token = app(TenantApiTokenService::class)
            ->create($tenant, 'Leads', [TenantApiAbility::LEADS_READ->value]);

        $this->getJson('/api/v1/ping/leads', ['Authorization' => 'Bearer '.$token->plainTextToken])
            ->assertSuccessful()
            ->assertJson(['status' => 'ok']);
    }

    public function test_token_expiration_comes_from_the_config(): void
    {
        $tenant = Tenant::factory()->create();
        $service = app(TenantApiTokenService::class);

        config(['funnel.api.token_expiration_days' => 0]);
        $service->create($tenant, 'Unbefristet', TenantApiAbility::values());
        $this->assertNull($tenant->tokens()->latest('id')->firstOrFail()->expires_at);

        config(['funnel.api.token_expiration_days' => 30]);
        $service->create($tenant, 'Befristet', TenantApiAbility::values());
        $this->assertTrue(
            $tenant->tokens()->latest('id')->firstOrFail()->expires_at->isSameDay(now()->addDays(30)),
        );
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();

        config(['funnel.api.token_expiration_days' => 1]);
        $token = app(TenantApiTokenService::class)
            ->create($tenant, 'Kurzlebig', TenantApiAbility::values());

        $this->travel(2)->days();

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$token->plainTextToken])
            ->assertUnauthorized();
    }

    public function test_the_token_limit_from_the_config_is_enforced(): void
    {
        $tenant = Tenant::factory()->create();
        $service = app(TenantApiTokenService::class);

        config(['funnel.api.max_tokens_per_tenant' => 2]);

        $service->create($tenant, 'Eins', TenantApiAbility::values());
        $service->create($tenant, 'Zwei', TenantApiAbility::values());

        $this->expectException(TenantApiTokenLimitReachedException::class);

        $service->create($tenant, 'Drei', TenantApiAbility::values());
    }

    public function test_creating_a_token_is_written_to_the_audit_log(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createUser($tenant);
        $this->actingAs($user);

        $token = app(TenantApiTokenService::class)->create($tenant, 'CRM-Anbindung', [
            TenantApiAbility::LEADS_READ->value,
            'leads:delete-everything',
        ]);

        $entry = AuditLog::query()->where('action', AuditAction::API_TOKEN_CREATED)->sole();

        $this->assertSame($tenant->id, $entry->tenant_id);
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame((string) $token->accessToken->id, $entry->subject_id);
        $this->assertSame('CRM-Anbindung', $entry->payload['name']);

        // Nur bereinigte Abilities, und niemals der Klartext des Tokens.
        $this->assertSame([TenantApiAbility::LEADS_READ->value], $entry->payload['abilities']);
        $this->assertStringNotContainsString(
            $token->plainTextToken,
            (string) json_encode($entry->payload),
        );
    }

    public function test_revoking_a_token_is_written_to_the_audit_log(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createUser($tenant);
        $this->actingAs($user);

        $service = app(TenantApiTokenService::class);
        $service->create($tenant, 'Widerruf-Kandidat', [TenantApiAbility::FUNNELS_READ->value]);
        $tokenId = $tenant->tokens()->firstOrFail()->id;

        $this->assertTrue($service->revoke($tenant, $tokenId));

        $entry = AuditLog::query()->where('action', AuditAction::API_TOKEN_DELETED)->sole();

        $this->assertSame($tenant->id, $entry->tenant_id);
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame((string) $tokenId, $entry->subject_id);
        $this->assertSame('Widerruf-Kandidat', $entry->payload['name']);
        $this->assertSame([TenantApiAbility::FUNNELS_READ->value], $entry->payload['abilities']);
    }

    public function test_a_failed_revocation_writes_no_audit_entry(): void
    {
        $own = Tenant::factory()->create();
        $foreign = Tenant::factory()->create();

        $service = app(TenantApiTokenService::class);
        $service->create($foreign, 'Fremdes Token', TenantApiAbility::values());
        $foreignTokenId = $foreign->tokens()->firstOrFail()->id;

        $this->assertFalse($service->revoke($own, $foreignTokenId));

        $this->assertSame(
            0,
            AuditLog::query()->where('action', AuditAction::API_TOKEN_DELETED)->count(),
        );
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
