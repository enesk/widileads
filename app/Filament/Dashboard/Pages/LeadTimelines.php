<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Constants\LeadState;
use App\Models\Funnel;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LeadTimelineReport;
use App\Services\TenantTypeService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
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
 * Zeitverlauf der Leads (FB-071, seit FB-090 mit Filament-Komponenten).
 *
 * Die Reihe kommt als Aggregat aus dem LeadTimelineReport; leere Zeitraeume
 * erscheinen als Null, nicht als Luecke. Die Auswahl steht weiterhin in der
 * Adresszeile, damit sich eine Ansicht weitergeben laesst.
 */
class LeadTimelines extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.dashboard.pages.lead-timelines';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 4;

    #[Url(as: 'raster', except: 'day')]
    public string $granularity = 'day';

    #[Url(as: 'nach', except: 'none')]
    public string $breakdown = 'none';

    #[Url(as: 'funnel', except: '')]
    public string $funnelId = '';

    #[Url(as: 'zustand', except: '')]
    public string $leadState = '';

    #[Url(as: 'von', except: '')]
    public string $from = '';

    #[Url(as: 'bis', except: '')]
    public string $until = '';

    public function getHeading(): string|Htmlable
    {
        return __('timeline.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('timeline.heading');
    }

    public static function getNavigationLabel(): string
    {
        return __('timeline.nav_label');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant || ! auth()->user() instanceof User) {
            return false;
        }

        return app(TenantTypeService::class)->canManageFunnels($tenant);
    }

    public function filters(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('timeline.filters'))
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('granularity')
                            ->label(__('timeline.granularity'))
                            ->options($this->labelled(LeadTimelineReport::GRANULARITIES, 'timeline.granularities.'))
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->live(),
                        Select::make('breakdown')
                            ->label(__('timeline.breakdown'))
                            ->options($this->labelled(LeadTimelineReport::BREAKDOWNS, 'timeline.breakdowns.'))
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->live(),
                        Select::make('funnelId')
                            ->label(__('timeline.funnel'))
                            ->options(fn (): array => $this->funnels())
                            ->placeholder(__('timeline.all'))
                            ->native(false)
                            ->live(),
                        Select::make('leadState')
                            ->label(__('timeline.state'))
                            ->options(fn (): array => LeadState::labels())
                            ->placeholder(__('timeline.all'))
                            ->native(false)
                            ->live(),
                        DatePicker::make('from')
                            ->label(__('timeline.from'))
                            ->live(),
                        DatePicker::make('until')
                            ->label(__('timeline.until'))
                            ->live(),
                    ]),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        $report = $this->report();

        $columns = [
            TextColumn::make('label')
                ->label(__('timeline.series'))
                ->weight('medium'),
        ];

        foreach ($report['buckets'] as $bucket) {
            $columns[] = TextColumn::make('bucket_'.$bucket)
                ->label($bucket)
                ->alignEnd()
                ->state(fn (array $record): int => (int) ($record['values'][$bucket] ?? 0));
        }

        $columns[] = TextColumn::make('total')
            ->label(__('timeline.total'))
            ->alignEnd()
            ->weight('bold');

        return $table
            ->records(fn (): Collection => collect($report['series']))
            ->heading(__('timeline.heading'))
            ->description(__('timeline.total').': '.$report['total'])
            ->emptyStateHeading(__('timeline.empty'))
            ->paginated(false)
            ->columns($columns);
    }

    /**
     * @return array{buckets: list<string>, series: list<array{label: string, values: array<string, int>, total: int}>, total: int}
     */
    private function report(): array
    {
        return app(LeadTimelineReport::class)->for(
            $this->tenant(),
            $this->granularity,
            $this->breakdown,
            [
                'funnel_id' => $this->funnelId,
                'lead_state' => $this->leadState,
                'from' => $this->from === '' ? null : Carbon::parse($this->from)->startOfDay(),
                'until' => $this->until === '' ? null : Carbon::parse($this->until)->endOfDay(),
            ],
        );
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

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private function labelled(array $values, string $prefix): array
    {
        $options = [];

        foreach ($values as $value) {
            $options[$value] = __($prefix.$value);
        }

        return $options;
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }
}
