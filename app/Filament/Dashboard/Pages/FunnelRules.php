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
 * Huelle fuer den Regel- und Ergebnis-Editor (FB-016).
 *
 * Wie bei FB-015 und FB-017: nur Routing, Navigation und Zugriff, der Inhalt
 * ist eine gewoehnliche Livewire-Komponente.
 */
class FunnelRules extends Page
{
    protected string $view = 'filament.dashboard.pages.funnel-rules';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    public Funnel $funnel;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'funnels/{funnel}/regeln';
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
        return __('builder.rules.title', ['funnel' => $this->funnel->name]);
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
