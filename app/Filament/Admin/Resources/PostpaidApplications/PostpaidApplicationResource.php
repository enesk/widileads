<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PostpaidApplications;

use App\Constants\PurchaseStatus;
use App\Filament\Admin\Resources\PostpaidApplications\Pages\ListPostpaidApplications;
use App\Filament\Admin\Resources\PostpaidApplications\Pages\ViewPostpaidApplication;
use App\Filament\Admin\Resources\Wallets\WalletResource;
use App\Models\LeadPurchase;
use App\Models\PaymentMethod;
use App\Models\PostpaidApplication;
use App\Models\Tenant;
use App\Models\Wallet;
use App\Services\Wallet\PostpaidService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * LP-POSTPAID-012: Die Antrags-Queue fuer Pay as you go.
 *
 * Entschieden wird von Hand, weil am Ende ein Forderungsrisiko steht. Damit
 * die Entscheidung nicht aus dem Bauch kommt, steht neben dem Antrag alles,
 * was sie traegt: der Eignungs-Schnappschuss mit jeder Regel und ihrem Wert,
 * das hinterlegte Zahlungsmittel und die Kaufhistorie des Kaeufers.
 *
 * Der Schnappschuss stammt aus dem Antrag und wird nicht neu berechnet: Er
 * belegt, was zum Zeitpunkt der Antragstellung galt (LP-POSTPAID-006). Die
 * Kaufhistorie daneben ist tagesaktuell -- sie ist keine Regel, sondern das
 * Bild, das der Admin sich macht.
 *
 * Beide Entscheidungen laufen ueber den PostpaidService: Zahlungsmodus,
 * Kreditrahmen, Beleg, Audit-Eintrag und die Mail an den Kaeufer gehoeren
 * zusammen, und das steht dort.
 */
class PostpaidApplicationResource extends Resource
{
    protected static ?string $model = PostpaidApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 12;

    /** Zeitraum (Tage) der Umsatzangabe in der Kaufhistorie. */
    private const REVENUE_DAYS = 90;

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.marketplace');
    }

    public static function getModelLabel(): string
    {
        return __('marketplace.wallet.admin.postpaid.application.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('marketplace.wallet.admin.postpaid.application.resource.plural_label');
    }

    /**
     * Offene Antraege in der Navigation: Der Kaeufer wartet auf eine Antwort,
     * und ein uebersehener Antrag ist ein verlorener Kunde.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = PostpaidApplication::query()->pending()->count();

        return $pending === 0 ? null : (string) $pending;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('marketplace.wallet.admin.postpaid.application.sections.application'))
                ->schema([
                    TextEntry::make('requested_at')
                        ->label(__('marketplace.wallet.admin.postpaid.application.fields.requested_at'))
                        ->dateTime(config('app.datetime_format')),
                    TextEntry::make('buyer')
                        ->label(__('marketplace.wallet.admin.postpaid.application.fields.buyer'))
                        ->state(fn (PostpaidApplication $record): string => self::buyerName($record)),
                    TextEntry::make('workspace')
                        ->label(__('marketplace.wallet.admin.postpaid.application.fields.workspace'))
                        ->state(fn (PostpaidApplication $record): string => self::workspaceReference($record)),
                    TextEntry::make('status')
                        ->label(__('marketplace.wallet.admin.postpaid.application.fields.status'))
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => self::statusLabel($state))
                        ->color(fn (string $state): string => self::statusColor($state)),
                    TextEntry::make('decided_at')
                        ->label(__('marketplace.wallet.admin.postpaid.application.fields.decided_at'))
                        ->dateTime(config('app.datetime_format'))
                        ->placeholder('—'),
                    TextEntry::make('decidedBy.name')
                        ->label(__('marketplace.wallet.admin.postpaid.application.fields.decided_by'))
                        ->placeholder('—'),
                    TextEntry::make('note')
                        ->label(__('marketplace.wallet.admin.postpaid.application.fields.note'))
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])
                ->columns(3),

            Section::make(__('marketplace.wallet.admin.postpaid.application.sections.eligibility'))
                ->description(__('marketplace.wallet.admin.postpaid.application.snapshot_hint'))
                ->schema([
                    KeyValueEntry::make('eligibility_snapshot')
                        ->label('')
                        ->keyLabel(__('marketplace.wallet.admin.postpaid.application.fields.rule'))
                        ->valueLabel(__('marketplace.wallet.admin.postpaid.application.fields.value'))
                        ->state(fn (PostpaidApplication $record): array => self::snapshotRows($record))
                        ->columnSpanFull(),
                ]),

            Section::make(__('marketplace.wallet.admin.postpaid.application.sections.context'))
                ->schema([
                    TextEntry::make('payment_method')
                        ->label(__('marketplace.wallet.admin.postpaid.application.fields.payment_method'))
                        ->state(fn (PostpaidApplication $record): string => self::paymentMethodLabel($record->wallet)),
                    TextEntry::make('wallet_balance')
                        ->label(__('marketplace.wallet.admin.fields.balance'))
                        ->state(fn (PostpaidApplication $record): string => WalletResource::money(
                            (int) $record->wallet->balance_cents,
                        )),
                    KeyValueEntry::make('purchase_history')
                        ->label(__('marketplace.wallet.admin.postpaid.application.fields.purchase_history'))
                        ->keyLabel(__('marketplace.wallet.admin.postpaid.application.fields.rule'))
                        ->valueLabel(__('marketplace.wallet.admin.postpaid.application.fields.value'))
                        ->state(fn (PostpaidApplication $record): array => self::purchaseHistory($record->wallet->owner))
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['wallet.owner', 'wallet.paymentMethods', 'decidedBy']))
            ->defaultSort('requested_at', 'asc')
            ->emptyStateHeading(__('marketplace.wallet.admin.postpaid.application.empty_heading'))
            ->emptyStateDescription(__('marketplace.wallet.admin.postpaid.application.empty_description'))
            ->columns([
                TextColumn::make('requested_at')
                    ->label(__('marketplace.wallet.admin.postpaid.application.fields.requested_at'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable(),
                TextColumn::make('buyer')
                    ->label(__('marketplace.wallet.admin.postpaid.application.fields.buyer'))
                    ->state(fn (PostpaidApplication $record): string => self::buyerName($record))
                    ->description(fn (PostpaidApplication $record): string => self::workspaceReference($record)),
                TextColumn::make('payment_method')
                    ->label(__('marketplace.wallet.admin.postpaid.application.fields.payment_method'))
                    ->state(fn (PostpaidApplication $record): string => self::paymentMethodLabel($record->wallet)),
                TextColumn::make('captured_purchases')
                    ->label(__('marketplace.wallet.admin.postpaid.application.fields.captured_purchases'))
                    ->state(fn (PostpaidApplication $record): string => self::snapshotValue($record, 'captured_purchases')),
                TextColumn::make('status')
                    ->label(__('marketplace.wallet.admin.postpaid.application.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabel($state))
                    ->color(fn (string $state): string => self::statusColor($state))
                    ->sortable(),
                TextColumn::make('decidedBy.name')
                    ->label(__('marketplace.wallet.admin.postpaid.application.fields.decided_by'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('marketplace.wallet.admin.postpaid.application.fields.status'))
                    ->options(fn (): array => self::statusOptions())
                    // Arbeitsliste: Offenes zuerst, Entschiedenes auf Nachfrage.
                    ->default(PostpaidApplication::STATUS_REQUESTED),
            ])
            ->recordActions([
                ViewAction::make(),
                self::approveAction(),
                self::rejectAction(),
            ])
            ->toolbarActions([]);
    }

    /**
     * Freigabe mit editierbarem Kreditrahmen. Vorbelegt ist die Vorgabe aus
     * config('wallet.postpaid.default_credit_limit_cents') -- der Regelfall
     * soll ein Klick sein, die Abweichung eine bewusste Eingabe.
     */
    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('marketplace.wallet.admin.postpaid.application.actions.approve'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->modalDescription(__('marketplace.wallet.admin.postpaid.application.actions.approve_confirm'))
            ->schema([
                TextInput::make('credit_limit_cents')
                    ->label(__('marketplace.wallet.admin.postpaid.fields.credit_limit_cents'))
                    ->helperText(__('marketplace.wallet.admin.postpaid.hints.credit_limit_cents'))
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->default(fn (): int => (int) config('wallet.postpaid.default_credit_limit_cents')),
            ])
            ->visible(fn (PostpaidApplication $record): bool => $record->isPending())
            ->action(fn (array $data, PostpaidApplication $record) => app(PostpaidService::class)
                ->approve($record, WalletResource::actingAdmin(), (int) $data['credit_limit_cents']))
            ->successNotificationTitle(__('marketplace.wallet.admin.postpaid.application.actions.approved'));
    }

    /**
     * Ablehnung mit Pflichtnotiz. Die Notiz bleibt intern: Der Kaeufer erfaehrt
     * nur, wann er wieder beantragen darf (PostpaidService::reject()).
     */
    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('marketplace.wallet.admin.postpaid.application.actions.reject'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->modalDescription(__('marketplace.wallet.admin.postpaid.application.actions.reject_confirm'))
            ->schema([
                Textarea::make('note')
                    ->label(__('marketplace.wallet.admin.postpaid.application.fields.note'))
                    ->helperText(__('marketplace.wallet.admin.postpaid.application.hints.note_required'))
                    ->required()
                    ->minLength(10)
                    ->maxLength(1000),
            ])
            ->visible(fn (PostpaidApplication $record): bool => $record->isPending())
            ->action(fn (array $data, PostpaidApplication $record) => app(PostpaidService::class)
                ->reject($record, WalletResource::actingAdmin(), $data['note']))
            ->successNotificationTitle(__('marketplace.wallet.admin.postpaid.application.actions.rejected'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPostpaidApplications::route('/'),
            'view' => ViewPostpaidApplication::route('/{record}'),
        ];
    }

    /**
     * Der Eignungs-Schnappschuss als lesbare Liste: jede Regel mit dem Wert,
     * der zum Zeitpunkt des Antrags galt, und der Huerde daneben.
     *
     * @return array<string, string>
     */
    public static function snapshotRows(PostpaidApplication $application): array
    {
        $snapshot = $application->eligibility_snapshot;
        $key = fn (string $name): string => __('marketplace.wallet.admin.postpaid.application.snapshot.'.$name);
        $int = fn (string $name): int => (int) ($snapshot[$name] ?? 0);

        $rows = [
            $key('eligible') => ($snapshot['eligible'] ?? false)
                ? __('marketplace.wallet.admin.postpaid.application.snapshot.eligible_yes')
                : __('marketplace.wallet.admin.postpaid.application.snapshot.eligible_no'),
            $key('captured_purchases') => __('marketplace.wallet.admin.postpaid.application.snapshot.of_required', [
                'value' => (string) $int('captured_purchases'),
                'required' => (string) $int('min_captured_purchases'),
            ]),
            $key('account_age_days') => __('marketplace.wallet.admin.postpaid.application.snapshot.of_required', [
                'value' => (string) $int('account_age_days'),
                'required' => (string) $int('min_account_age_days'),
            ]),
            $key('failed_settlements') => (string) $int('failed_settlements'),
            $key('chargebacks') => (string) $int('chargebacks'),
            $key('clean_history_days') => (string) $int('clean_history_days'),
            $key('balance_cents') => WalletResource::money($int('balance_cents')),
            $key('open_amount_cents') => WalletResource::money($int('open_amount_cents')),
            $key('purchase_blocked') => self::yesNo((bool) ($snapshot['purchase_blocked'] ?? false)),
            $key('postpaid_disabled_reason') => self::downgradeReasonLabel($snapshot['postpaid_disabled_reason'] ?? null),
        ];

        $reasons = $snapshot['reasons'] ?? [];

        if (is_array($reasons) && $reasons !== []) {
            $rows[$key('reasons')] = implode(' ', array_map(strval(...), $reasons));
        }

        return $rows;
    }

    /**
     * Die Kaufhistorie des Kaeufers, tagesaktuell: Zahl der Kaeufe je Stand
     * und der Umsatz der letzten 90 Tage. Gezaehlt wird an abgerechneten
     * Kaeufen -- reserviert ist noch kein Geld geflossen.
     *
     * @return array<string, string>
     */
    public static function purchaseHistory(?Tenant $buyer): array
    {
        $key = fn (string $name): string => __('marketplace.wallet.admin.postpaid.application.history.'.$name);

        if (! $buyer instanceof Tenant) {
            return [$key('captured') => '—'];
        }

        $counts = LeadPurchase::query()
            ->where('buyer_tenant_id', $buyer->getKey())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $revenueCents = (int) LeadPurchase::query()
            ->where('buyer_tenant_id', $buyer->getKey())
            ->where('status', PurchaseStatus::CAPTURED->value)
            ->where('captured_at', '>=', Carbon::now()->subDays(self::REVENUE_DAYS))
            ->sum('price_cents');

        return [
            $key('captured') => (string) (int) ($counts[PurchaseStatus::CAPTURED->value] ?? 0),
            $key('released') => (string) (int) ($counts[PurchaseStatus::RELEASED->value] ?? 0),
            $key('refunded') => (string) (int) ($counts[PurchaseStatus::REFUNDED->value] ?? 0),
            $key('revenue') => WalletResource::money($revenueCents),
        ];
    }

    /**
     * Typ und letzte vier Stellen des Standard-Zahlungsmittels -- ohne
     * einsatzbereites Mittel kann nicht eingezogen werden.
     */
    public static function paymentMethodLabel(?Wallet $wallet): string
    {
        $method = $wallet?->defaultPaymentMethod()->first();

        if (! $method instanceof PaymentMethod) {
            return __('marketplace.wallet.admin.postpaid.no_payment_method');
        }

        return $method->type->label().' •••• '.$method->last4;
    }

    public static function statusLabel(string $status): string
    {
        return __('marketplace.wallet.admin.postpaid.application.status.'.$status);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            PostpaidApplication::STATUS_REQUESTED => self::statusLabel(PostpaidApplication::STATUS_REQUESTED),
            PostpaidApplication::STATUS_APPROVED => self::statusLabel(PostpaidApplication::STATUS_APPROVED),
            PostpaidApplication::STATUS_REJECTED => self::statusLabel(PostpaidApplication::STATUS_REJECTED),
        ];
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            PostpaidApplication::STATUS_APPROVED => 'success',
            PostpaidApplication::STATUS_REJECTED => 'gray',
            default => 'warning',
        };
    }

    private static function buyerName(PostpaidApplication $application): string
    {
        $buyer = $application->buyer();

        return $buyer instanceof Tenant ? $buyer->name : '—';
    }

    /**
     * Der Workspace hinter dem Antrag. Genannt wird die UUID, weil sie im
     * Portal in der Adresszeile steht und damit die Angabe ist, mit der sich
     * eine Rueckfrage des Kaeufers zuordnen laesst.
     */
    private static function workspaceReference(PostpaidApplication $application): string
    {
        $buyer = $application->buyer();

        return $buyer instanceof Tenant ? (string) $buyer->uuid : '—';
    }

    private static function snapshotValue(PostpaidApplication $application, string $key): string
    {
        $value = $application->eligibility_snapshot[$key] ?? null;

        return $value === null ? '—' : (string) $value;
    }

    private static function yesNo(bool $value): string
    {
        return $value
            ? __('marketplace.wallet.admin.postpaid.yes')
            : __('marketplace.wallet.admin.postpaid.no');
    }

    private static function downgradeReasonLabel(mixed $reason): string
    {
        if (! is_string($reason) || $reason === '') {
            return '—';
        }

        return __('marketplace.wallet.postpaid.mail.downgraded.reasons.'.$reason);
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
