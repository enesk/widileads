<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Models\Funnel;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FunnelConversionReport;
use App\Services\TenantTypeService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Trichter je Funnel (FB-070, seit FB-090 mit Filament-Komponenten).
 *
 * Gerechnet wird nichts hier: Alle Zahlen kommen als Aggregate aus dem
 * FunnelConversionReport. Die Auswahl steht weiterhin in der Adresszeile,
 * damit sich eine Ansicht weitergeben laesst.
 */
class FunnelReports extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.dashboard.pages.funnel-reports';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?int $navigationSort = 3;

    #[Url(as: 'funnel', except: '')]
    public string $funnelId = '';

    #[Url(as: 'von', except: '')]
    public string $from = '';

    #[Url(as: 'bis', except: '')]
    public string $until = '';

    public function getHeading(): string|Htmlable
    {
        return __('reports.funnel.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('reports.funnel.heading');
    }

    public static function getNavigationLabel(): string
    {
        return __('reports.funnel.nav_label');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant || ! auth()->user() instanceof User) {
            return false;
        }

        return app(TenantTypeService::class)->canManageFunnels($tenant);
    }

    public function mount(): void
    {
        $funnels = $this->funnels();

        if ($this->funnelId === '' && $funnels !== []) {
            $this->funnelId = (string) array_key_first($funnels);
        }
    }

    public function filters(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('timeline.filters'))
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('funnelId')
                            ->label(__('reports.funnel.funnel'))
                            ->options(fn (): array => $this->funnels())
                            ->native(false)
                            ->live(),
                        DatePicker::make('from')
                            ->label(__('reports.funnel.from'))
                            ->live(),
                        DatePicker::make('until')
                            ->label(__('reports.funnel.until'))
                            ->live(),
                    ]),
                ]),
        ]);
    }

    public function summary(Schema $schema): Schema
    {
        $report = $this->report();

        return $schema->components([
            Section::make(__('reports.funnel.heading'))
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('views')
                            ->label(__('reports.funnel.views'))
                            ->size('lg')
                            ->weight('bold')
                            ->state((string) ($report['views'] ?? 0)),
                        TextEntry::make('submissions')
                            ->label(__('reports.funnel.submissions'))
                            ->size('lg')
                            ->weight('bold')
                            ->state((string) ($report['submissions'] ?? 0)),
                        TextEntry::make('abandoned')
                            ->label(__('reports.funnel.abandoned'))
                            ->size('lg')
                            ->weight('bold')
                            ->state((string) ($report['abandoned'] ?? 0)),
                        TextEntry::make('completion_rate')
                            ->label(__('reports.funnel.completion_rate'))
                            ->size('lg')
                            ->weight('bold')
                            ->state($this->percent((float) ($report['completion_rate'] ?? 0))),
                    ]),
                ])
                ->visible($report !== null),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => collect($this->report()['steps'] ?? []))
            ->heading(__('reports.funnel.steps'))
            ->emptyStateHeading($this->report() === null
                ? __('reports.funnel.no_funnel')
                : __('reports.funnel.no_steps'))
            ->paginated(false)
            ->columns([
                TextColumn::make('title')
                    ->label(__('reports.funnel.step'))
                    ->state(fn (array $record): string => $record['position'].'. '.$record['title']),
                TextColumn::make('views')
                    ->label(__('reports.funnel.step_views'))
                    ->alignEnd(),
                TextColumn::make('completions')
                    ->label(__('reports.funnel.step_completions'))
                    ->alignEnd(),
                TextColumn::make('drop_offs')
                    ->label(__('reports.funnel.drop_offs'))
                    ->alignEnd(),
                TextColumn::make('drop_off_rate')
                    ->label(__('reports.funnel.drop_off_rate'))
                    ->alignEnd()
                    ->badge()
                    // Ein Schritt, an dem die Haelfte abspringt, ist der Grund,
                    // warum jemand diese Seite aufruft -- er wird markiert.
                    ->color(fn (array $record): string => $record['drop_off_rate'] >= 50 && $record['views'] > 0
                        ? 'warning'
                        : 'gray')
                    ->state(fn (array $record): string => $this->percent((float) $record['drop_off_rate'])),
            ]);
    }

    /**
     * Der Trichter des gewaehlten Funnels -- oder null, solange keiner gewaehlt
     * ist.
     *
     * @return array{views: int, submissions: int, abandoned: int, completion_rate: float, steps: list<array<string, mixed>>}|null
     */
    private function report(): ?array
    {
        $funnel = $this->selectedFunnel();

        if (! $funnel instanceof Funnel) {
            return null;
        }

        return app(FunnelConversionReport::class)->for(
            $funnel,
            $this->from === '' ? null : Carbon::parse($this->from)->startOfDay(),
            $this->until === '' ? null : Carbon::parse($this->until)->endOfDay(),
        );
    }

    private function selectedFunnel(): ?Funnel
    {
        if ($this->funnelId === '') {
            return null;
        }

        return Funnel::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenant()->getKey())
            ->with('currentVersion')
            ->find($this->funnelId);
    }

    /**
     * @return array<int, string>
     */
    private function funnels(): array
    {
        return Funnel::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenant()->getKey())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function percent(float $value): string
    {
        return number_format($value, 1, ',', '.').' %';
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }
}
