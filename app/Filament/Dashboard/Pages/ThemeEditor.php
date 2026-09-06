<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Models\Funnel;
use App\Models\Tenant;
use App\Services\TenantPermissionService;
use App\Services\TenantTypeService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Huelle fuer den Theme-Editor (FB-017).
 *
 * Wie beim Builder aus FB-015 steuert die Seite nur Routing, Navigation und
 * Zugriff bei; der Inhalt ist eine gewoehnliche Livewire-Komponente.
 */
class ThemeEditor extends Page
{
    protected string $view = 'filament.dashboard.pages.theme-editor';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedSwatch;

    public Funnel $funnel;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'funnels/{funnel}/theme';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(Funnel $funnel): void
    {
        $this->funnel = $funnel;
    }

    public function getHeading(): string|Htmlable
    {
        return $this->funnel->name;
    }

    public function getTitle(): string|Htmlable
    {
        return __('builder.theme.title', ['funnel' => $this->funnel->name]);
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        if (! app(TenantTypeService::class)->canManageFunnels($tenant)) {
            return false;
        }

        return app(TenantPermissionService::class)->tenantUserHasPermissionTo(
            $tenant,
            auth()->user(),
            TenancyPermissionConstants::PERMISSION_MANAGE_FUNNELS,
        );
    }
}
