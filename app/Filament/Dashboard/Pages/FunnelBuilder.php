<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Models\Funnel;
use App\Models\Tenant;
use App\Services\TenantPermissionService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

/**
 * Huelle fuer den Funnel-Builder (FB-015).
 *
 * Die Seite steuert ausschliesslich Routing, Navigation und Zugriff bei; der
 * gesamte Inhalt ist eine gewoehnliche Livewire-Komponente. Siehe Abschnitt 5
 * in docs/funnel-builder/agent-prompt.md.
 */
class FunnelBuilder extends Page
{
    protected string $view = 'filament.dashboard.pages.funnel-builder';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedSquares2x2;

    public Funnel $funnel;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'funnels/{funnel}/builder';
    }

    /**
     * Der Builder haengt immer an einem konkreten Funnel und taugt deshalb
     * nicht als Navigationseintrag.
     */
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
        return __('builder.title', ['funnel' => $this->funnel->name]);
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        // Nur Betreiber verwalten Funnels - Kaeufer sehen den Bereich nicht
        // (FB-002).
        if (! Gate::allows('funnels.manage', $tenant)) {
            return false;
        }

        return app(TenantPermissionService::class)->tenantUserHasPermissionTo(
            $tenant,
            auth()->user(),
            TenancyPermissionConstants::PERMISSION_MANAGE_FUNNELS,
        );
    }
}
