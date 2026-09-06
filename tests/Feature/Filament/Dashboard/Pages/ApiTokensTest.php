<?php

namespace Tests\Feature\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Constants\TenantApiAbility;
use App\Filament\Dashboard\Pages\ApiTokens;
use App\Livewire\Filament\Dashboard\ApiTokens as ApiTokensLivewire;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantApiTokenService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * FB-006: Seite "API-Zugaenge" im Tenant-Dashboard.
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

    public function test_a_token_can_be_created_and_is_shown_once(): void
    {
        [$tenant] = $this->actingAsTokenManager();

        $component = Livewire::test(ApiTokensLivewire::class)
            ->callAction(TestAction::make('create')->table(), [
                'name' => 'CRM-Anbindung',
                'abilities' => [TenantApiAbility::LEADS_READ->value],
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'CRM-Anbindung',
            'tokenable_type' => Tenant::class,
            'tokenable_id' => $tenant->id,
        ]);

        $plainTextToken = $component->get('plainTextToken');
        $this->assertNotNull($plainTextToken);
        $this->assertNotNull(PersonalAccessToken::findToken($plainTextToken));

        // Nach dem Ausblenden ist der Klartext weg und nicht wiederherstellbar.
        $component->call('dismissPlainTextToken')->assertSet('plainTextToken', null);
    }

    public function test_the_table_only_lists_tokens_of_the_active_tenant(): void
    {
        [$tenant] = $this->actingAsTokenManager();
        $foreign = $this->createTenant();

        $service = app(TenantApiTokenService::class);
        $service->create($tenant, 'Eigenes Token', TenantApiAbility::values());
        $service->create($foreign, 'Fremdes Token', TenantApiAbility::values());

        Livewire::test(ApiTokensLivewire::class)
            ->assertCanSeeTableRecords($tenant->tokens()->get())
            ->assertCanNotSeeTableRecords($foreign->tokens()->get());
    }

    public function test_a_token_can_be_revoked(): void
    {
        [$tenant] = $this->actingAsTokenManager();

        app(TenantApiTokenService::class)->create($tenant, 'CRM', TenantApiAbility::values());
        $token = $tenant->tokens()->firstOrFail();

        Livewire::test(ApiTokensLivewire::class)
            ->callAction(TestAction::make('revoke')->table($token));

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
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
