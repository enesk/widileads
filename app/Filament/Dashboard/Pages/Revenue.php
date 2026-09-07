<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Models\Tenant;
use App\Models\User;
use App\Services\OperatorRevenueReport;
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
 * Umsatzuebersicht des Betreibers (FB-072, seit FB-090 mit
 * Filament-Komponenten).
 *
 * Alle Betraege kommen in Cent aus dem OperatorRevenueReport und werden erst
 * fuer die Anzeige formatiert. Gerechnet wird hier nichts.
 *
 * Je Funnel und je Kaeufer stehen in derselben Tabelle -- umgeschaltet wird
 * ueber die Aufschluesselung. Eine Seite mit zwei Filament-Tabellen gibt es
 * nicht, und zwei Zahlenreihen nebeneinander wollte hier ohnehin niemand
 * vergleichen.
 */
class Revenue extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.dashboard.pages.revenue';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 5;

    #[Url(as: 'von', except: '')]
    public string $from = '';

    #[Url(as: 'bis', except: '')]
    public string $until = '';

    #[Url(as: 'nach', except: 'by_funnel')]
    public string $breakdown = 'by_funnel';

    public function getHeading(): string|Htmlable
    {
        return __('operator_revenue.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('operator_revenue.heading');
    }

    public static function getNavigationLabel(): string
    {
        return __('operator_revenue.nav_label');
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
                        DatePicker::make('from')
                            ->label(__('operator_revenue.from'))
                            ->live(),
                        DatePicker::make('until')
                            ->label(__('operator_revenue.until'))
                            ->live(),
                        Select::make('breakdown')
                            ->label(__('timeline.breakdown'))
                            ->options([
                                'by_funnel' => __('operator_revenue.by_funnel'),
                                'by_buyer' => __('operator_revenue.by_buyer'),
                            ])
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->live(),
                    ]),
                ]),
        ]);
    }

    public function summary(Schema $schema): Schema
    {
        $totals = $this->report()['totals'];

        $sections = [];

        foreach ($totals as $index => $total) {
            $sections[] = Section::make(__('operator_revenue.heading').' ('.$total['currency'].')')
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('sold_'.$index)
                            ->label(__('operator_revenue.sold'))
                            ->size('lg')
                            ->weight('bold')
                            ->state((string) $total['sold']),
                        TextEntry::make('revenue_'.$index)
                            ->label(__('operator_revenue.revenue'))
                            ->size('lg')
                            ->weight('bold')
                            ->state($this->money($total['revenue_cents'], $total['currency'])),
                        TextEntry::make('refunds_'.$index)
                            ->label(__('operator_revenue.refunds'))
                            ->size('lg')
                            ->weight('bold')
                            ->state($total['refunds'].' · '.$this->money($total['refunded_cents'], $total['currency'])),
                        TextEntry::make('net_'.$index)
                            ->label(__('operator_revenue.net'))
                            ->size('lg')
                            ->weight('bold')
                            ->state($this->money($total['net_cents'], $total['currency'])),
                    ]),
                ]);
        }

        return $schema->components($sections);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => collect($this->rows()))
            ->heading(__('operator_revenue.'.($this->breakdown === 'by_buyer' ? 'by_buyer' : 'by_funnel')))
            ->emptyStateHeading(__('operator_revenue.empty'))
            ->paginated(false)
            ->columns([
                TextColumn::make('label')
                    ->label(__('operator_revenue.'.($this->breakdown === 'by_buyer' ? 'by_buyer' : 'by_funnel')))
                    ->weight('medium')
                    ->placeholder(__('operator_revenue.without_funnel')),
                TextColumn::make('sold')
                    ->label(__('operator_revenue.sold'))
                    ->alignEnd(),
                TextColumn::make('revenue_cents')
                    ->label(__('operator_revenue.revenue'))
                    ->alignEnd()
                    ->state(fn (array $record): string => $this->money($record['revenue_cents'], $record['currency'])),
                TextColumn::make('refunded_cents')
                    ->label(__('operator_revenue.refunds'))
                    ->alignEnd()
                    ->state(fn (array $record): string => $record['refunds'].' · '.$this->money($record['refunded_cents'], $record['currency'])),
                TextColumn::make('net_cents')
                    ->label(__('operator_revenue.net'))
                    ->alignEnd()
                    ->weight('bold')
                    ->state(fn (array $record): string => $this->money($record['net_cents'], $record['currency'])),
            ]);
    }

    /**
     * Die Zeilen der gewaehlten Aufschluesselung.
     *
     * @return list<array{label: string, currency: string, sold: int, revenue_cents: int, refunds: int, refunded_cents: int, net_cents: int}>
     */
    private function rows(): array
    {
        $report = $this->report();

        return $this->breakdown === 'by_buyer' ? $report['by_buyer'] : $report['by_funnel'];
    }

    /**
     * @return array{by_funnel: list<array<string, mixed>>, by_buyer: list<array<string, mixed>>, totals: list<array<string, mixed>>}
     */
    private function report(): array
    {
        return app(OperatorRevenueReport::class)->for(
            $this->tenant(),
            $this->from === '' ? null : Carbon::parse($this->from)->startOfDay(),
            $this->until === '' ? null : Carbon::parse($this->until)->endOfDay(),
        );
    }

    private function money(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2, ',', '.').' '.($currency === 'EUR' ? '€' : $currency);
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }
}
