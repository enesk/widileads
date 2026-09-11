<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Wallets;

use App\Constants\WalletOwnerType;
use App\Constants\WalletTransactionType;
use App\Filament\Admin\Resources\Wallets\Pages\ListWallets;
use App\Filament\Admin\Resources\Wallets\Pages\ViewWallet;
use App\Filament\Admin\Resources\Wallets\RelationManagers\TransactionsRelationManager;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * LP-WALLET-013: Alle Geldtoepfe des Marktplatzes im Blick.
 *
 * Ansehen, und als einzige schreibende Handlung die manuelle Korrektur. Salden
 * lassen sich hier nicht bearbeiten und Buchungen nicht loeschen -- eine
 * Fehlbuchung wird gegengebucht, nicht wegradiert. Die Korrektur laeuft
 * deshalb ueber den WalletService und nicht ueber ein Formular auf dem Model:
 * nur dort werden Vorzeichen, Deckung und Saldenfortschreibung in einer
 * Transaktion gehalten (LP-WALLET-005).
 *
 * Die Liste ist nach Wallet-Typ gruppiert. Jede Gruppe traegt eine Summenzeile
 * -- die Summe der Kaeufer-Wallets ist das Geld, das die Plattform ihren
 * Kunden schuldet, die des Plattform-Wallets die kumulierte Provision.
 */
class WalletResource extends Resource
{
    protected static ?string $model = Wallet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.marketplace');
    }

    public static function getModelLabel(): string
    {
        return __('marketplace.wallet.admin.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('marketplace.wallet.admin.resource.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description(__('marketplace.wallet.admin.resource.read_only'))
                ->schema([
                    TextEntry::make('owner_name')
                        ->label(__('marketplace.wallet.admin.fields.owner'))
                        ->state(fn (Wallet $record): string => self::ownerName($record)),
                    TextEntry::make('owner_type')
                        ->label(__('marketplace.wallet.admin.fields.owner_type'))
                        ->badge()
                        ->formatStateUsing(fn (WalletOwnerType $state): string => self::ownerTypeLabel($state)),
                    TextEntry::make('balance_cents')
                        ->label(__('marketplace.wallet.admin.fields.balance'))
                        ->formatStateUsing(fn (int $state): string => self::money($state))
                        ->color(fn (int $state): string => $state < 0 ? 'danger' : 'gray'),
                    TextEntry::make('reserved_cents')
                        ->label(__('marketplace.wallet.admin.fields.reserved'))
                        ->formatStateUsing(fn (int $state): string => self::money($state)),
                    TextEntry::make('available_cents')
                        ->label(__('marketplace.wallet.admin.fields.available'))
                        ->state(fn (Wallet $record): string => self::money($record->available_cents))
                        ->color(fn (Wallet $record): string => $record->available_cents < 0 ? 'danger' : 'gray'),
                    TextEntry::make('currency')
                        ->label(__('marketplace.wallet.admin.fields.currency')),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('owner'))
            ->defaultSort('balance_cents', 'desc')
            ->defaultGroup('owner_type')
            ->groups([
                Group::make('owner_type')
                    ->label(__('marketplace.wallet.admin.fields.owner_type'))
                    ->getTitleFromRecordUsing(fn (Wallet $record): string => self::ownerTypeLabel($record->owner_type)),
            ])
            ->columns([
                TextColumn::make('id')
                    ->label(__('marketplace.wallet.admin.fields.id'))
                    ->sortable(),
                TextColumn::make('owner.name')
                    ->label(__('marketplace.wallet.admin.fields.owner'))
                    ->state(fn (Wallet $record): string => self::ownerName($record))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('owner_type')
                    ->label(__('marketplace.wallet.admin.fields.owner_type'))
                    ->badge()
                    ->formatStateUsing(fn (WalletOwnerType $state): string => self::ownerTypeLabel($state))
                    ->color(fn (WalletOwnerType $state): string => match ($state) {
                        WalletOwnerType::BUYER => 'info',
                        WalletOwnerType::SELLER => 'success',
                        WalletOwnerType::PLATFORM => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('balance_cents')
                    ->label(__('marketplace.wallet.admin.fields.balance'))
                    ->formatStateUsing(fn (int $state): string => self::money($state))
                    // Ein negativer Saldo ist kein Schoenheitsfehler, sondern
                    // offenes Geld -- er muss sofort ins Auge fallen.
                    ->color(fn (int $state): string => $state < 0 ? 'danger' : 'gray')
                    ->weight(fn (int $state): ?string => $state < 0 ? 'bold' : null)
                    ->sortable()
                    ->summarize(self::moneySummarizer('balance_cents', 'balance_cents')),
                TextColumn::make('reserved_cents')
                    ->label(__('marketplace.wallet.admin.fields.reserved'))
                    ->formatStateUsing(fn (int $state): string => self::money($state))
                    ->sortable()
                    ->summarize(self::moneySummarizer('reserved_cents', 'reserved_cents')),
                TextColumn::make('available_cents')
                    ->label(__('marketplace.wallet.admin.fields.available'))
                    ->state(fn (Wallet $record): string => self::money($record->available_cents))
                    ->color(fn (Wallet $record): string => $record->available_cents < 0 ? 'danger' : 'gray')
                    ->weight(fn (Wallet $record): ?string => $record->available_cents < 0 ? 'bold' : null)
                    ->summarize(self::moneySummarizer('available_cents', 'balance_cents - reserved_cents')),
            ])
            ->filters([
                SelectFilter::make('owner_type')
                    ->label(__('marketplace.wallet.admin.fields.owner_type'))
                    ->options(fn (): array => self::ownerTypeOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                self::adjustAction(),
            ])
            ->toolbarActions([]);
    }

    /**
     * Die manuelle Korrektur -- auch vom Detail der Wallet aus verwendet.
     *
     * `allowNegative` ist bewusst eine eigene Entscheidung und nicht der
     * Regelfall: Eine Abbuchung, die den Saldo unter null druecken wuerde, ist
     * meistens ein Tippfehler und soll dann scheitern. Wer das Minus will --
     * etwa nach einer Erstattung, deren Erloes laengst ausgezahlt ist --, sagt
     * es ausdruecklich.
     */
    public static function adjustAction(): Action
    {
        return Action::make('adjust')
            ->label(__('marketplace.wallet.admin.actions.adjust'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('warning')
            ->modalDescription(__('marketplace.wallet.admin.actions.adjust_description'))
            ->modalSubmitActionLabel(__('marketplace.wallet.admin.actions.adjust_submit'))
            ->requiresConfirmation()
            ->schema([
                TextInput::make('amount_cents')
                    ->label(__('marketplace.wallet.admin.fields.amount_cents'))
                    ->helperText(__('marketplace.wallet.admin.hints.amount_cents'))
                    ->numeric()
                    ->required()
                    ->rule('not_in:0'),
                Textarea::make('reason')
                    ->label(__('marketplace.wallet.admin.fields.reason'))
                    ->helperText(__('marketplace.wallet.admin.hints.reason'))
                    ->required()
                    ->minLength(10)
                    ->maxLength(1000),
                Toggle::make('allow_negative')
                    ->label(__('marketplace.wallet.admin.fields.allow_negative'))
                    ->helperText(__('marketplace.wallet.admin.hints.allow_negative'))
                    ->default(false),
            ])
            ->action(function (array $data, Wallet $record): void {
                $admin = self::actingAdmin();
                $amountCents = (int) $data['amount_cents'];

                app(WalletService::class)->post(
                    wallet: $record,
                    type: WalletTransactionType::ADJUSTMENT,
                    amountCents: $amountCents,
                    description: __('marketplace.wallet.admin.descriptions.adjustment', [
                        'reason' => $data['reason'],
                    ]),
                    meta: [
                        'reason' => $data['reason'],
                        'adjusted_by' => (int) $admin->getKey(),
                    ],
                    allowNegative: (bool) ($data['allow_negative'] ?? false),
                    createdBy: (int) $admin->getKey(),
                );
            })
            ->successNotificationTitle(__('marketplace.wallet.admin.actions.adjusted'));
    }

    /**
     * @return list<class-string>
     */
    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWallets::route('/'),
            'view' => ViewWallet::route('/{record}'),
        ];
    }

    /**
     * Der Name hinter einem Wallet. Das Plattform-Wallet hat keinen Mandanten.
     */
    public static function ownerName(Wallet $wallet): string
    {
        if ($wallet->owner_type === WalletOwnerType::PLATFORM) {
            return __('marketplace.wallet.admin.platform_owner');
        }

        $owner = $wallet->owner;

        return $owner instanceof Tenant ? $owner->name : '—';
    }

    public static function ownerTypeLabel(WalletOwnerType $type): string
    {
        return __('marketplace.wallet.admin.owner_type.'.$type->value);
    }

    /**
     * @return array<string, string>
     */
    public static function ownerTypeOptions(): array
    {
        $options = [];

        foreach (WalletOwnerType::cases() as $case) {
            $options[$case->value] = self::ownerTypeLabel($case);
        }

        return $options;
    }

    /**
     * Cent als Betrag in der Schreibweise des Admin-Panels. Vorzeichenbehaftet,
     * damit eine Rueckbuchung auch als solche zu erkennen ist.
     */
    public static function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' '.((string) config('wallet.currency') === 'EUR' ? '€' : (string) config('wallet.currency'));
    }

    /**
     * Der angemeldete Admin. Jede Korrektur traegt ihn als Verursacher.
     */
    public static function actingAdmin(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Summenzeile in Geld statt in Cent. `$expression` ist der SQL-Ausdruck,
     * weil `available_cents` kein Feld der Tabelle ist, sondern die Differenz
     * der beiden Saldenspalten.
     */
    private static function moneySummarizer(string $id, string $expression): Summarizer
    {
        return Summarizer::make($id)
            ->label(__('marketplace.wallet.admin.fields.sum'))
            ->using(fn (Builder $query): int => (int) $query->sum(DB::raw($expression)))
            ->formatStateUsing(fn (int $state): string => self::money($state));
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
}
