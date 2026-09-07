<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AuditLogs;

use App\Constants\AuditAction;
use App\Filament\Admin\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * FB-005: Read-only-Ansicht des Audit-Logs im Admin-Panel.
 *
 * Die Resource bietet bewusst nur Lesen und Ansehen an: Audit-Eintraege sind
 * unveraenderlich, deshalb gibt es weder Anlegen noch Bearbeiten noch Loeschen
 * (auch nicht als Massenaktion).
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.security');
    }

    public static function getModelLabel(): string
    {
        return __('funnel.audit.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('funnel.audit.resource.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description(__('funnel.audit.hints.read_only'))
                ->schema([
                    TextEntry::make('created_at')
                        ->label(__('funnel.audit.fields.created_at'))
                        ->dateTime(),
                    TextEntry::make('action')
                        ->label(__('funnel.audit.fields.action'))
                        ->formatStateUsing(fn (AuditAction $state): string => $state->label()),
                    TextEntry::make('tenant.name')
                        ->label(__('funnel.audit.fields.tenant'))
                        ->placeholder('-'),
                    TextEntry::make('user.name')
                        ->label(__('funnel.audit.fields.user'))
                        ->placeholder('-'),
                    TextEntry::make('subject_type')
                        ->label(__('funnel.audit.fields.subject_type'))
                        ->placeholder('-'),
                    TextEntry::make('subject_id')
                        ->label(__('funnel.audit.fields.subject_id'))
                        ->placeholder('-'),
                    TextEntry::make('ip_hash')
                        ->label(__('funnel.audit.fields.ip_hash'))
                        ->helperText(__('funnel.audit.hints.ip_hash'))
                        ->placeholder('-'),
                    TextEntry::make('payload')
                        ->label(__('funnel.audit.fields.payload'))
                        ->placeholder('-')
                        ->formatStateUsing(fn (mixed $state): string => self::formatPayload($state))
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('funnel.audit.resource.empty_heading'))
            ->emptyStateDescription(__('funnel.audit.resource.empty_description'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('funnel.audit.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('action')
                    ->label(__('funnel.audit.fields.action'))
                    ->badge()
                    ->formatStateUsing(fn (AuditAction $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('tenant.name')
                    ->label(__('funnel.audit.fields.tenant'))
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label(__('funnel.audit.fields.user'))
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('subject_type')
                    ->label(__('funnel.audit.fields.subject'))
                    ->placeholder('-')
                    ->formatStateUsing(fn (AuditLog $record): string => $record->subject_type === null
                        ? '-'
                        : class_basename($record->subject_type).' #'.$record->subject_id)
                    ->toggleable(),
                TextColumn::make('ip_hash')
                    ->label(__('funnel.audit.fields.ip_hash'))
                    ->placeholder('-')
                    ->limit(16)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label(__('funnel.audit.filters.action'))
                    ->options(fn (): array => AuditAction::labels()),
                SelectFilter::make('tenant')
                    ->label(__('funnel.audit.filters.tenant'))
                    ->relationship('tenant', 'name'),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label(__('funnel.audit.filters.from')),
                        DatePicker::make('until')->label(__('funnel.audit.filters.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $from): Builder => $query->whereDate('created_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $query, string $until): Builder => $query->whereDate('created_at', '<=', $until))),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
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

    private static function formatPayload(mixed $payload): string
    {
        if (! is_array($payload) || $payload === []) {
            return '-';
        }

        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
