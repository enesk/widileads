<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Wallets;

use App\Constants\PaymentMode;
use App\Constants\WalletOwnerType;
use App\Constants\WalletTransactionType;
use App\Events\Postpaid\PostpaidDowngradeRequested;
use App\Filament\Admin\Resources\Wallets\Pages\ListWallets;
use App\Filament\Admin\Resources\Wallets\Pages\ViewWallet;
use App\Filament\Admin\Resources\Wallets\RelationManagers\TransactionsRelationManager;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\PostpaidService;
use App\Services\Wallet\SettlementService;
use App\Services\Wallet\WalletService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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
                    TextEntry::make('payment_mode')
                        ->label(__('marketplace.wallet.admin.postpaid.fields.payment_mode'))
                        ->badge()
                        ->formatStateUsing(fn (PaymentMode $state): string => self::paymentModeLabel($state))
                        ->color(fn (PaymentMode $state): string => $state === PaymentMode::POSTPAID ? 'info' : 'gray'),
                    TextEntry::make('credit_limit_cents')
                        ->label(__('marketplace.wallet.admin.postpaid.fields.credit_limit'))
                        ->formatStateUsing(fn (int $state): string => self::money($state)),
                    TextEntry::make('open_amount_cents')
                        ->label(__('marketplace.wallet.admin.postpaid.fields.open_amount'))
                        ->state(fn (Wallet $record): string => self::money($record->open_amount_cents))
                        ->color(fn (Wallet $record): string => $record->open_amount_cents > 0 ? 'danger' : 'gray'),
                    TextEntry::make('purchase_blocked')
                        ->label(__('marketplace.wallet.admin.postpaid.fields.blocked'))
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state
                            ? __('marketplace.wallet.admin.postpaid.blocked_yes')
                            : __('marketplace.wallet.admin.postpaid.blocked_no'))
                        ->color(fn (bool $state): string => $state ? 'danger' : 'gray'),
                    TextEntry::make('postpaid_disabled_reason')
                        ->label(__('marketplace.wallet.admin.postpaid.fields.disabled_reason'))
                        ->placeholder('—')
                        ->formatStateUsing(fn (string $state): string => __('marketplace.wallet.postpaid.mail.downgraded.reasons.'.$state)),
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
                TextColumn::make('payment_mode')
                    ->label(__('marketplace.wallet.admin.postpaid.fields.payment_mode'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentMode $state): string => self::paymentModeLabel($state))
                    ->color(fn (PaymentMode $state): string => $state === PaymentMode::POSTPAID ? 'info' : 'gray')
                    ->sortable(),
                TextColumn::make('credit_limit_cents')
                    ->label(__('marketplace.wallet.admin.postpaid.fields.credit_limit'))
                    ->formatStateUsing(fn (int $state): string => self::money($state))
                    ->sortable(),
                TextColumn::make('open_amount_cents')
                    ->label(__('marketplace.wallet.admin.postpaid.fields.open_amount'))
                    ->state(fn (Wallet $record): string => self::money($record->open_amount_cents))
                    ->color(fn (Wallet $record): string => $record->open_amount_cents > 0 ? 'danger' : 'gray')
                    ->weight(fn (Wallet $record): ?string => $record->open_amount_cents > 0 ? 'bold' : null)
                    // Die Summenzeile ist die Antwort auf die Frage, wie viel
                    // Geld draussen ist. Gerechnet wird in SQL und nicht ueber
                    // die geladenen Zeilen, damit die Summe die ganze Auswahl
                    // meint und nicht die aktuelle Seite.
                    ->summarize(self::moneySummarizer(
                        'open_amount_cents',
                        'GREATEST(-balance_cents, 0)',
                        __('marketplace.wallet.admin.postpaid.fields.open_total'),
                    )),
                TextColumn::make('purchase_blocked')
                    ->label(__('marketplace.wallet.admin.postpaid.fields.blocked'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('marketplace.wallet.admin.postpaid.blocked_yes')
                        : __('marketplace.wallet.admin.postpaid.blocked_no'))
                    ->color(fn (bool $state): string => $state ? 'danger' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('owner_type')
                    ->label(__('marketplace.wallet.admin.fields.owner_type'))
                    ->options(fn (): array => self::ownerTypeOptions()),
                SelectFilter::make('payment_mode')
                    ->label(__('marketplace.wallet.admin.postpaid.fields.payment_mode'))
                    ->options(fn (): array => self::paymentModeOptions()),
                TernaryFilter::make('purchase_blocked')
                    ->label(__('marketplace.wallet.admin.postpaid.fields.blocked')),
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
     * Kreditrahmen aendern (LP-POSTPAID-012).
     *
     * Nur bei einem freigeschalteten Kauf-Wallet: Ein Rahmen an einem
     * Prepaid-Wallet waere ein Kredit ohne Vereinbarung. Gebucht wird nichts
     * -- der Rahmen ist eine Erlaubnis, kein Guthaben.
     */
    public static function changeCreditLimitAction(): Action
    {
        return Action::make('changeCreditLimit')
            ->label(__('marketplace.wallet.admin.postpaid.actions.change_credit_limit'))
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('warning')
            ->modalDescription(__('marketplace.wallet.admin.postpaid.actions.change_credit_limit_description'))
            ->schema([
                TextInput::make('credit_limit_cents')
                    ->label(__('marketplace.wallet.admin.postpaid.fields.credit_limit_cents'))
                    ->helperText(__('marketplace.wallet.admin.postpaid.hints.credit_limit_cents'))
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->default(fn (Wallet $record): int => (int) $record->credit_limit_cents),
            ])
            ->visible(fn (Wallet $record): bool => $record->owner_type === WalletOwnerType::BUYER && $record->isPostpaid())
            ->action(fn (array $data, Wallet $record) => app(PostpaidService::class)
                ->updateCreditLimit($record, (int) $data['credit_limit_cents'], self::actingAdmin()))
            ->successNotificationTitle(__('marketplace.wallet.admin.postpaid.actions.credit_limit_changed'));
    }

    /**
     * Offenen Betrag sofort einziehen (LP-POSTPAID-012).
     *
     * Sichtbar nur, wenn es etwas einzuziehen gibt und kein Einzug laeuft: Ein
     * zweiter wuerde denselben Betrag ein zweites Mal abbuchen. Der Weg ist
     * derselbe wie beim Termin, nur der Ausloeser ist ein anderer.
     */
    public static function settleNowAction(): Action
    {
        return Action::make('settleNow')
            ->label(__('marketplace.wallet.admin.postpaid.actions.settle_now'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription(__('marketplace.wallet.admin.postpaid.actions.settle_now_description'))
            ->visible(fn (Wallet $record): bool => $record->owner_type === WalletOwnerType::BUYER
                && $record->open_amount_cents > 0
                && $record->openSettlement()->doesntExist())
            ->action(fn (Wallet $record) => app(SettlementService::class)
                ->createManual($record, self::actingAdmin()))
            ->successNotificationTitle(__('marketplace.wallet.admin.postpaid.actions.settled'));
    }

    /**
     * Rueckstufung auf Vorauszahlung von Hand (LP-POSTPAID-012).
     *
     * Der Grund ist Pflicht und wird aus der Liste der bekannten Gruende
     * gewaehlt: An ihm haengen Gebuehr, Kaufsperre und der Text der Mail an
     * den Kaeufer. Die Gebuehr ist vorbelegt, laesst sich aber aendern -- auch
     * auf 0, wenn der Fall es hergibt.
     */
    public static function downgradeAction(): Action
    {
        return Action::make('downgrade')
            ->label(__('marketplace.wallet.admin.postpaid.actions.downgrade'))
            ->icon(Heroicon::OutlinedArrowDownCircle)
            ->color('danger')
            ->modalDescription(__('marketplace.wallet.admin.postpaid.actions.downgrade_description'))
            ->schema([
                Select::make('reason')
                    ->label(__('marketplace.wallet.admin.postpaid.fields.downgrade_reason'))
                    ->options(fn (): array => self::downgradeReasonOptions())
                    ->helperText(__('marketplace.wallet.admin.postpaid.hints.downgrade_reason'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
                        'fee_cents',
                        $state === null ? 0 : PostpaidService::defaultFeeCentsFor($state),
                    )),
                TextInput::make('fee_cents')
                    ->label(__('marketplace.wallet.admin.postpaid.fields.fee_cents'))
                    ->helperText(__('marketplace.wallet.admin.postpaid.hints.fee_cents'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
            ])
            ->visible(fn (Wallet $record): bool => $record->owner_type === WalletOwnerType::BUYER && $record->isPostpaid())
            ->action(fn (array $data, Wallet $record) => app(PostpaidService::class)
                ->downgrade($record, (string) $data['reason'], (int) ($data['fee_cents'] ?? 0)))
            ->successNotificationTitle(__('marketplace.wallet.admin.postpaid.actions.downgraded'));
    }

    /**
     * Pay as you go nach einer Rueckstufung wieder freischalten
     * (LP-POSTPAID-012).
     *
     * Nur sichtbar, wenn tatsaechlich zurueckgestuft wurde -- die erste
     * Freischaltung laeuft ueber den Antrag des Kaeufers und nicht hier. Die
     * Eignung wird bewusst nicht erneut verlangt; die Bestaetigung macht
     * deutlich, dass das eine Entscheidung gegen die Regel ist.
     */
    public static function reenablePostpaidAction(): Action
    {
        return Action::make('reenablePostpaid')
            ->label(__('marketplace.wallet.admin.postpaid.actions.reenable'))
            ->icon(Heroicon::OutlinedArrowUpCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('marketplace.wallet.admin.postpaid.actions.reenable_description'))
            ->schema([
                TextInput::make('credit_limit_cents')
                    ->label(__('marketplace.wallet.admin.postpaid.fields.credit_limit_cents'))
                    ->helperText(__('marketplace.wallet.admin.postpaid.hints.credit_limit_cents'))
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->default(fn (): int => (int) config('wallet.postpaid.default_credit_limit_cents')),
            ])
            ->visible(fn (Wallet $record): bool => $record->owner_type === WalletOwnerType::BUYER
                && ! $record->isPostpaid()
                && $record->postpaid_disabled_at !== null)
            ->action(fn (array $data, Wallet $record) => app(PostpaidService::class)
                ->reenable($record, self::actingAdmin(), (int) $data['credit_limit_cents']))
            ->successNotificationTitle(__('marketplace.wallet.admin.postpaid.actions.reenabled'));
    }

    public static function paymentModeLabel(PaymentMode $mode): string
    {
        return __('marketplace.wallet.admin.postpaid.payment_mode.'.$mode->value);
    }

    /**
     * @return array<string, string>
     */
    public static function paymentModeOptions(): array
    {
        $options = [];

        foreach (PaymentMode::cases() as $case) {
            $options[$case->value] = self::paymentModeLabel($case);
        }

        return $options;
    }

    /**
     * Die Rueckstufungsgruende zur Auswahl. Gewaehlt wird aus der Liste und
     * nicht frei getippt: Am Grund haengen Gebuehr, Kaufsperre und der Text
     * der Mail an den Kaeufer.
     *
     * @return array<string, string>
     */
    public static function downgradeReasonOptions(): array
    {
        $reasons = [
            PostpaidDowngradeRequested::REASON_SETTLEMENT_FAILED,
            PostpaidDowngradeRequested::REASON_SEPA_RETURN,
            PostpaidDowngradeRequested::REASON_CHARGEBACK,
            PostpaidDowngradeRequested::REASON_NO_PAYMENT_METHOD,
            PostpaidDowngradeRequested::REASON_PAYMENT_METHOD_REVOKED,
        ];

        $options = [];

        foreach ($reasons as $reason) {
            $options[$reason] = __('marketplace.wallet.postpaid.mail.downgraded.reasons.'.$reason);
        }

        return $options;
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
    private static function moneySummarizer(string $id, string $expression, ?string $label = null): Summarizer
    {
        return Summarizer::make($id)
            ->label($label ?? __('marketplace.wallet.admin.fields.sum'))
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
