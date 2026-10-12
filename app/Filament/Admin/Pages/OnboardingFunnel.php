<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\OnboardingFunnelChart;
use App\Filament\Admin\Widgets\StuckWorkspacesTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * LP-ONBOARD-015: Wo neue Kaeufer haengen bleiben.
 *
 * Die Huelle fuer zwei Widgets und sonst nichts: der Trichter mit seinen neun
 * Stufen (OnboardingFunnelChart) und die Liste der Workspaces, die gerade
 * stehen (StuckWorkspacesTable). Jedes Widget bringt seinen eigenen Filter mit
 * -- der Zeitraum gehoert zum Trichter, die 48-Stunden-Schwelle zur Liste.
 *
 * Die Seite ist eine reine Ansicht. Sie liest ausschliesslich ueber
 * OnboardingFunnelQuery; kein Onboarding-Zustand laesst sich von hier aus
 * aendern.
 */
class OnboardingFunnel extends Page
{
    protected string $view = 'filament.admin.pages.onboarding-funnel';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static ?int $navigationSort = 10;

    public function getHeading(): string|Htmlable
    {
        return __('reports.onboarding.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('reports.onboarding.heading');
    }

    public static function getNavigationLabel(): string
    {
        return __('reports.onboarding.nav_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.reports');
    }

    /**
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            OnboardingFunnelChart::class,
            StuckWorkspacesTable::class,
        ];
    }

    /**
     * Eine Spalte: Beide Widgets sind breite Tabellen, nebeneinander waere
     * keine der beiden lesbar.
     */
    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
