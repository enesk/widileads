<?php

namespace Tests\Feature\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Constants\TenantApiAbility;
use App\Filament\Dashboard\Pages\ApiTokens;
use App\Livewire\Dashboard\ApiTokens as ApiTokensLivewire;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantApiTokenService;
use Filament\Facades\Filament;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * FB-006: Seite "API-Zugaenge" im Tenant-Dashboard (reines Livewire).
 */
class ApiTokensTest extends FeatureTest
{
    public function test_page_requires_the_manage_api_tokens_permission(): void
    {
        $tenant = $this->createTenant();
        $this->actingAs($this->createUser($tenant));

        $this->withExceptionHandling();

        $this->get(ApiTokens::getUrl(panel: 'dashboard', tenant: $tenant))
            ->assertForbidden();
    }

    public function test_page_is_reachable_with_the_permission(): void
    {
        [$tenant] = $this->actingAsTokenManager();

        $this->get(ApiTokens::getUrl(panel: 'dashboard', tenant: $tenant))
            ->assertSuccessful();
    }

    public function test_a_token_is_shown_in_plain_text_exactly_once(): void
    {
        [$tenant] = $this->actingAsTokenManager();

        $component = Livewire::test(ApiTokensLivewire::class)
            ->set('name', 'CRM-Anbindung')
            ->set('abilities', [TenantApiAbility::LEADS_READ->value])
            ->call('createToken')
            ->assertHasNoErrors();

        $plainTextToken = $component->get('plainTextToken');

        $this->assertNotNull($plainTextToken);
        $component->assertSee($plainTextToken);
        $this->assertNotNull(PersonalAccessToken::findToken($plainTextToken));

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'CRM-Anbindung',
            'tokenable_type' => Tenant::class,
            'tokenable_id' => $tenant->id,
        ]);

        // Nach dem Ausblenden ist der Klartext weg - und ein erneutes Rendern
        // der Seite bringt ihn nicht zurueck.
        $component->call('dismissPlainTextToken')
            ->assertSet('plainTextToken', null)
            ->assertDontSee($plainTextToken);

        Livewire::test(ApiTokensLivewire::class)
            ->assertSet('plainTextToken', null)
            ->assertDontSee($plainTextToken);
    }

    public function test_creating_a_token_requires_a_name_and_at_least_one_ability(): void
    {
        $this->actingAsTokenManager();

        Livewire::test(ApiTokensLivewire::class)
            ->set('name', '')
            ->set('abilities', [])
            ->call('createToken')
            ->assertHasErrors(['name', 'abilities']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unknown_abilities_are_rejected_by_validation(): void
    {
        $this->actingAsTokenManager();

        Livewire::test(ApiTokensLivewire::class)
            ->set('name', 'Boeses Token')
            ->set('abilities', ['leads:delete-everything'])
            ->call('createToken')
            ->assertHasErrors(['abilities.0']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_the_page_only_lists_tokens_of_the_active_tenant(): void
    {
        [$tenant] = $this->actingAsTokenManager();
        $foreign = $this->createTenant();

        $service = app(TenantApiTokenService::class);
        $service->create($tenant, 'Eigenes Token', TenantApiAbility::values());
        $service->create($foreign, 'Fremdes Token', TenantApiAbility::values());

        Livewire::test(ApiTokensLivewire::class)
            ->assertSee('Eigenes Token')
            ->assertDontSee('Fremdes Token');
    }

    public function test_a_token_can_be_revoked(): void
    {
        [$tenant] = $this->actingAsTokenManager();

        // Bewusst ein Name, der in keinem Hilfetext der Seite vorkommt.
        app(TenantApiTokenService::class)->create($tenant, 'Widerruf-Kandidat', TenantApiAbility::values());
        $token = $tenant->tokens()->firstOrFail();

        Livewire::test(ApiTokensLivewire::class)
            ->call('revoke', $token->id)
            ->assertDontSee('Widerruf-Kandidat');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_a_tenant_cannot_revoke_a_foreign_token_through_the_page(): void
    {
        $this->actingAsTokenManager();
        $foreign = $this->createTenant();

        app(TenantApiTokenService::class)->create($foreign, 'Fremdes Token', TenantApiAbility::values());
        $foreignToken = $foreign->tokens()->firstOrFail();

        Livewire::test(ApiTokensLivewire::class)->call('revoke', $foreignToken->id);

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreignToken->id]);
    }

    public function test_the_token_limit_from_the_config_is_enforced(): void
    {
        [$tenant] = $this->actingAsTokenManager();

        config(['funnel.api.max_tokens_per_tenant' => 1]);

        app(TenantApiTokenService::class)->create($tenant, 'Erstes', TenantApiAbility::values());

        Livewire::test(ApiTokensLivewire::class)
            ->set('name', 'Zweites')
            ->set('abilities', [TenantApiAbility::LEADS_READ->value])
            ->call('createToken')
            ->assertHasErrors('name');

        $this->assertSame(1, $tenant->tokens()->count());
    }

    /**
     * @return array{Tenant, User}
     */
    private function actingAsTokenManager(): array
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant, [TenancyPermissionConstants::PERMISSION_MANAGE_API_TOKENS]);

        $this->actingAs($user);
        Filament::setTenant($tenant, isQuiet: true);

        return [$tenant, $user];
    }
}
