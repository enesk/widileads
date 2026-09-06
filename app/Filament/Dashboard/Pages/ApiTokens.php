<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Models\Tenant;
use App\Services\TenantPermissionService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Verwaltung der API-Tokens des aktiven Tenants (FB-006).
 */
class ApiTokens extends Page
{
    protected string $view = 'filament.dashboard.pages.api-tokens';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedKey;

    public function getHeading(): string|Htmlable
    {
        return __('funnel.api_token.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('funnel.api_token.heading');
    }

    public static function getNavigationLabel(): string
    {
        return __('funnel.api_token.nav_label');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return app(TenantPermissionService::class)->tenantUserHasPermissionTo(
            $tenant,
            auth()->user(),
            TenancyPermissionConstants::PERMISSION_MANAGE_API_TOKENS
        );
    }
}
