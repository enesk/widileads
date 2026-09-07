<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CreditLedger;

use App\Constants\CreditLedgerType;
use App\Constants\TenantType;
use App\Filament\Admin\Resources\CreditLedger\Pages\ListCreditLedgerEntries;
use App\Models\CreditLedgerEntry;
use App\Models\Tenant;
use App\Services\CreditLedgerService;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * FB-052: Das Guthabenkonto im Admin-Panel.
 *
 * Ansehen und -- als einzige schreibende Handlung -- eine manuelle Korrektur
 * buchen. Aendern und Loeschen gibt es nicht: Buchungen sind unveraenderlich,
 * eine Fehlbuchung wird durch eine Gegenbuchung korrigiert.
 *
 * Die Korrektur ist zugleich der Weg fuer Guthaben auf Rechnung (Entscheidung 1
 * vom 2026-09-06): Der Plattform-Admin bucht das vereinbarte Guthaben von Hand,
 * es gibt bewusst keinen zweiten Zahlungsweg im Code.
 */
class CreditLedgerResource extends Resource
{
    protected static ?string $model = CreditLedgerEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 12;

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.marketplace');
    }

    public static function getModelLabel(): string
    {
        return __('marketplace.credit.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('marketplace.credit.resource.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    /**
     * Das Formular der manuellen Korrektur -- auch von der Seite aus verwendet.
     *
     * @return list<mixed>
     */
    public static function adjustmentFormSchema(): array
    {
        return [
            Select::make('tenant_id')
                ->label(__('marketplace.credit.fields.tenant'))
                ->options(fn (): array => Tenant::query()
                    ->where('type', TenantType::BUYER->value)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->required(),

            TextInput::make('credits')
                ->label(__('marketplace.credit.fields.credits'))
                ->helperText(__('marketplace.credit.hints.credits'))
                ->numeric()
                ->required()
                ->rule('not_in:0'),

            TextInput::make('amount_cents')
                ->label(__('marketplace.credit.fields.amount_cents'))
                ->helperText(__('marketplace.credit.hints.amount_cents'))
                ->numeric()
                ->minValue(0)
                ->nullable(),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description(__('marketplace.credit.resource.read_only'))
                ->schema([
                    TextEntry::make('created_at')
                        ->label(__('marketplace.credit.fields.created_at'))
                        ->dateTime(),
                    TextEntry::make('tenant.name')
                        ->label(__('marketplace.credit.fields.tenant')),
                    TextEntry::make('type')
                        ->label(__('marketplace.credit.fields.type'))
                        ->badge()
                        ->formatStateUsing(fn (CreditLedgerType $state): string => $state->label()),
                    TextEntry::make('credits')
                        ->label(__('marketplace.credit.fields.credits')),
                    TextEntry::make('amount_cents')
                        ->label(__('marketplace.credit.fields.amount_cents'))
                        ->placeholder('-'),
                    TextEntry::make('reference_type')
                        ->label(__('marketplace.credit.fields.reference'))
                        ->placeholder('-')
                        ->formatStateUsing(fn (CreditLedgerEntry $record): string => $record->reference_type === null
                            ? '-'
                            : class_basename($record->reference_type).' #'.$record->reference_id),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('marketplace.credit.resource.empty_heading'))
            ->emptyStateDescription(__('marketplace.credit.resource.empty_description'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('marketplace.credit.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('tenant.name')
                    ->label(__('marketplace.credit.fields.tenant'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('marketplace.credit.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (CreditLedgerType $state): string => $state->label())
                    ->color(fn (CreditLedgerType $state): string => match ($state) {
                        CreditLedgerType::PURCHASE => 'success',
                        CreditLedgerType::DEBIT => 'warning',
                        CreditLedgerType::REFUND => 'info',
                        CreditLedgerType::ADJUSTMENT => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('credits')
                    ->label(__('marketplace.credit.fields.credits'))
                    ->sortable()
                    // Das Vorzeichen ist die Aussage der Zeile und soll auch
                    // dann sichtbar sein, wenn es positiv ist.
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? '+'.$state : (string) $state)
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('amount_cents')
                    ->label(__('marketplace.credit.fields.amount_cents'))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('reference_type')
                    ->label(__('marketplace.credit.fields.reference'))
                    ->placeholder('-')
                    ->formatStateUsing(fn (CreditLedgerEntry $record): string => $record->reference_type === null
                        ? '-'
                        : class_basename($record->reference_type).' #'.$record->reference_id)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('marketplace.credit.fields.type'))
                    ->options(fn (): array => CreditLedgerType::options()),
                SelectFilter::make('tenant')
                    ->label(__('marketplace.credit.fields.tenant'))
                    ->relationship('tenant', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditLedgerEntries::route('/'),
        ];
    }

    /**
     * Guthaben eines Mandanten -- fuer die Anzeige in der Kopfzeile.
     */
    public static function balanceFor(int $tenantId): int
    {
        $tenant = Tenant::query()->find($tenantId);

        return $tenant === null ? 0 : app(CreditLedgerService::class)->balanceFor($tenant);
    }

    /**
     * Buchungen entstehen ueber den CreditLedgerService, nicht ueber ein
     * Formular -- nur dort werden Vorzeichen und Deckung geprueft.
     */
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
}
