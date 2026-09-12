<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Settlements;

use App\Constants\SettlementStatus;
use App\Filament\Admin\Resources\Settlements\Pages\ListSettlements;
use App\Filament\Admin\Resources\Wallets\WalletResource;
use App\Models\PaymentMethod;
use App\Models\Settlement;
use App\Models\Tenant;
use App\Services\Wallet\SettlementService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * LP-POSTPAID-012: Alle Postpaid-Einzuege im Blick.
 *
 * Die Liste beantwortet die eine Frage, die der Betreiber an einen Einzug hat:
 * Ist das Geld da, ist es unterwegs, oder ist es ausgefallen -- und wenn
 * ausgefallen, warum. Deshalb stehen Versuche, naechster Versuch und
 * Fehlergrund in der Zeile und nicht in einem Detail.
 *
 * Der Link auf den PaymentIntent fuehrt in das Dashboard des
 * Zahlungsanbieters, weil dort steht, was wir nicht speichern: der Weg der
 * Zahlung bei der Bank. Test- und Live-Umgebung haben verschiedene Adressen;
 * unterschieden wird am Schluessel, nicht an APP_ENV -- ein Testschluessel in
 * der Produktion kaeme sonst mit einem Link daher, der ins Leere fuehrt.
 *
 * Zustaende werden hier nicht bearbeitet. Der einzige Eingriff ist der erneute
 * Versuch, und der laeuft ueber den SettlementService.
 */
class SettlementResource extends Resource
{
    protected static ?string $model = Settlement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 13;

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.marketplace');
    }

    public static function getModelLabel(): string
    {
        return __('marketplace.wallet.admin.postpaid.settlement.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('marketplace.wallet.admin.postpaid.settlement.resource.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['wallet.owner', 'wallet.paymentMethods', 'paymentMethod']))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('marketplace.wallet.admin.postpaid.settlement.empty_heading'))
            ->emptyStateDescription(__('marketplace.wallet.admin.postpaid.settlement.empty_description'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('marketplace.wallet.admin.postpaid.settlement.fields.created_at'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable(),
                TextColumn::make('buyer')
                    ->label(__('marketplace.wallet.admin.postpaid.settlement.fields.buyer'))
                    ->state(fn (Settlement $record): string => self::buyerName($record))
                    ->url(fn (Settlement $record): string => WalletResource::getUrl('view', ['record' => $record->wallet])),
                TextColumn::make('amount_cents')
                    ->label(__('marketplace.wallet.admin.postpaid.settlement.fields.amount'))
                    ->formatStateUsing(fn (int $state): string => WalletResource::money($state))
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('marketplace.wallet.admin.postpaid.settlement.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (SettlementStatus $state): string => self::statusLabel($state))
                    ->color(fn (SettlementStatus $state): string => self::statusColor($state))
                    ->description(fn (Settlement $record): string => self::triggerLabel($record))
                    ->sortable(),
                TextColumn::make('attempts')
                    ->label(__('marketplace.wallet.admin.postpaid.settlement.fields.attempts'))
                    ->sortable(),
                TextColumn::make('next_attempt_at')
                    ->label(__('marketplace.wallet.admin.postpaid.settlement.fields.next_attempt_at'))
                    ->dateTime(config('app.datetime_format'))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('provider_payment_intent_id')
                    ->label(__('marketplace.wallet.admin.postpaid.settlement.fields.payment_intent'))
                    ->placeholder('—')
                    ->url(fn (Settlement $record): ?string => self::stripeUrl($record->provider_payment_intent_id))
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->copyable(),
                TextColumn::make('failure_reason')
                    ->label(__('marketplace.wallet.admin.postpaid.settlement.fields.failure_reason'))
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('invoice_reference')
                    ->label(__('marketplace.wallet.admin.postpaid.settlement.fields.invoice_reference'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('marketplace.wallet.admin.postpaid.settlement.fields.status'))
                    ->options(fn (): array => self::statusOptions()),
            ])
            ->recordActions([
                self::retryAction(),
            ])
            ->toolbarActions([]);
    }

    /**
     * Erneuter Versuch zu einem gescheiterten Einzug.
     *
     * Nur sichtbar, wenn das Wallet wieder ein einsatzbereites Zahlungsmittel
     * hat: Ohne Mittel wuerde der Versuch im selben Augenblick wieder
     * scheitern und dem Kaeufer eine zweite Mahnung schicken. Was tatsaechlich
     * eingezogen wird, ist der heute offene Betrag -- der SettlementService
     * setzt ihn neu, weil der Kaeufer inzwischen aufgeladen haben kann.
     */
    public static function retryAction(): Action
    {
        return Action::make('retry')
            ->label(__('marketplace.wallet.admin.postpaid.settlement.actions.retry'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(__('marketplace.wallet.admin.postpaid.settlement.actions.retry_confirm'))
            ->visible(fn (Settlement $record): bool => self::isRetryable($record))
            ->action(fn (Settlement $record) => app(SettlementService::class)
                ->retry($record, WalletResource::actingAdmin()))
            ->successNotificationTitle(__('marketplace.wallet.admin.postpaid.settlement.actions.retried'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSettlements::route('/'),
        ];
    }

    public static function statusLabel(SettlementStatus $status): string
    {
        return __('marketplace.wallet.admin.postpaid.settlement.status.'.$status->value);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        $options = [];

        foreach (SettlementStatus::cases() as $case) {
            $options[$case->value] = self::statusLabel($case);
        }

        return $options;
    }

    /**
     * Die Adresse der Zahlung im Dashboard des Zahlungsanbieters.
     */
    public static function stripeUrl(?string $paymentIntentId): ?string
    {
        if ($paymentIntentId === null || $paymentIntentId === '') {
            return null;
        }

        $secret = (string) config('services.stripe.secret_key');
        $prefix = str_starts_with($secret, 'sk_live_') ? '' : 'test/';

        return 'https://dashboard.stripe.com/'.$prefix.'payments/'.$paymentIntentId;
    }

    /**
     * Darf dieser Einzug wiederholt werden? Gescheitert, und das Wallet hat
     * wieder ein einsatzbereites Zahlungsmittel.
     */
    private static function isRetryable(Settlement $record): bool
    {
        if ($record->status !== SettlementStatus::FAILED) {
            return false;
        }

        return $record->wallet->defaultPaymentMethod()->first() instanceof PaymentMethod;
    }

    private static function statusColor(SettlementStatus $status): string
    {
        return match ($status) {
            SettlementStatus::PAID => 'success',
            SettlementStatus::FAILED, SettlementStatus::RETURNED => 'danger',
            SettlementStatus::PROCESSING => 'info',
            default => 'warning',
        };
    }

    private static function triggerLabel(Settlement $record): string
    {
        return __('marketplace.wallet.admin.postpaid.settlement.trigger.'.$record->trigger);
    }

    private static function buyerName(Settlement $record): string
    {
        $buyer = $record->buyer();

        return $buyer instanceof Tenant ? $buyer->name : '—';
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
