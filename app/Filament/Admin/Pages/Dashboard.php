<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\OnboardingFunnelChart;
use App\Filament\Admin\Widgets\StuckWorkspacesTable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;

    /**
     * Die Widgets des Onboarding-Trichters gehoeren nicht aufs Dashboard
     * (LP-ONBOARD-015).
     *
     * `discoverWidgets()` haengt jedes gefundene Widget hier ein, und die
     * beiden bringen ihren eigenen Zeitraum-Filter mit -- neben dem
     * Dashboard-Filter stuenden dann zwei Zeitraeume auf einer Seite, die
     * nichts voneinander wissen. Gezeigt werden sie auf
     * App\Filament\Admin\Pages\OnboardingFunnel.
     *
     * @return array<class-string | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        $pageBound = [
            OnboardingFunnelChart::class,
            StuckWorkspacesTable::class,
        ];

        return array_values(array_filter(
            parent::getWidgets(),
            static fn (mixed $widget): bool => ! (is_string($widget) && in_array($widget, $pageBound, strict: true)),
        ));
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make([
                DatePicker::make('start_date')
                    ->default(now()->subYear()->toDateString())
                    ->afterStateHydrated(function (DatePicker $component, ?string $state) {
                        if (! $state) {
                            $component->state(now()->subYear()->toDateString());
                        }
                    })
                    ->label(__('Start Date')),
                DatePicker::make('end_date')
                    ->default(date(now()->toDateString()))
                    ->afterStateHydrated(function (DatePicker $component, ?string $state) {
                        if (! $state) {
                            $component->state(now()->toDateString());
                        }
                    })
                    ->label(__('End Date')),
                Select::make('period')->label(__('Period'))->options([
                    'day' => __('Day'),
                    'week' => __('Week'),
                    'month' => __('Month'),
                    'year' => __('Year'),
                ])->default('month'),

            ])->columnSpanFull()
                ->columns(3),
        ]);
    }
}
