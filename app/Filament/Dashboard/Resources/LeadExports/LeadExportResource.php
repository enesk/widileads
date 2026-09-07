<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Resources\LeadExports;

use App\Constants\LeadExportStatus;
use App\Filament\Dashboard\Resources\LeadExports\Pages\ListLeadExports;
use App\Models\LeadExport;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LeadExportBuilder;
use App\Services\TenantTypeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

/**
 * Lead-Exporte (FB-073, seit FB-090 als Filament-Resource).
 *
 * Die Datei entsteht im Hintergrundjob, nicht hier. Diese Resource nimmt den
 * Auftrag entgegen und zeigt den Stand; der Download-Link wird beim Rendern
 * erzeugt und ist befristet.
 *
 * **Maskiert wird im LeadExportBuilder.** Welche Spalten ein Anfordernder im
 * Klartext bekommt, entscheidet dort `Lead::contactFor()` -- nicht die Auswahl
 * in diesem Formular.
 */
class LeadExportResource extends Resource
{
    protected static ?string $model = LeadExport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?int $navigationSort = 6;

    public static function getModelLabel(): string
    {
        return __('exports.nav_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('exports.heading');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.leads');
    }

    public static function getNavigationLabel(): string
    {
        return __('exports.nav_label');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant || ! auth()->user() instanceof User) {
            return false;
        }

        return app(TenantTypeService::class)->canManageFunnels($tenant);
    }

    public static function form(Schema $schema): Schema
    {
        // Ein Export wird angefordert, nicht bearbeitet -- die Spaltenauswahl
        // steht in der Aktion auf der Listenseite.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->description(__('exports.hint'))
            ->emptyStateHeading(__('exports.empty'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('exports.requested_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('requester.name')
                    ->label(__('exports.requested_by'))
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('exports.status_label'))
                    ->badge()
                    ->formatStateUsing(fn (LeadExportStatus $state): string => $state->label())
                    ->color(fn (LeadExportStatus $state): string => match ($state) {
                        LeadExportStatus::READY => 'success',
                        LeadExportStatus::FAILED => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (LeadExport $record): ?string => $record->failure_reason),
                TextColumn::make('columns')
                    ->label(__('exports.columns_legend'))
                    ->badge()
                    ->state(fn (LeadExport $record): array => array_map(
                        static fn (string $column): string => __('exports.columns.'.$column),
                        $record->columns,
                    ))
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->toggleable(),
                TextColumn::make('row_count')
                    ->label(__('exports.rows'))
                    ->alignEnd()
                    ->placeholder('-'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('exports.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->button()
                    ->visible(fn (LeadExport $record): bool => $record->isDownloadable())
                    ->url(fn (LeadExport $record): string => self::downloadLink($record)),
            ])
            ->toolbarActions([]);
    }

    /**
     * Der befristete Download-Link. Die Datei liegt nicht oeffentlich, sie wird
     * durch eine signierte Route ausgeliefert.
     */
    public static function downloadLink(LeadExport $export): string
    {
        return URL::temporarySignedRoute(
            'lead-export.download',
            now()->addMinutes((int) config('funnel.export.link_ttl_minutes')),
            ['export' => $export->uuid],
        );
    }

    /**
     * @return array<string, string>
     */
    public static function availableColumns(): array
    {
        $columns = [];

        foreach (LeadExportBuilder::COLUMNS as $column) {
            $columns[$column] = (string) __('exports.columns.'.$column);
        }

        return $columns;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeadExports::route('/'),
        ];
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->where('tenant_id', Filament::getTenant()?->getKey())
            ->with('requester');
    }
}
