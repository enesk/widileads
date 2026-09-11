<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PayoutRequests;

use App\Constants\PayoutStatus;
use App\Filament\Admin\Resources\PayoutRequests\Pages\ListPayoutRequests;
use App\Filament\Admin\Resources\Wallets\WalletResource;
use App\Models\PayoutRequest;
use App\Models\Tenant;
use App\Services\Wallet\PayoutService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
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
 * LP-WALLET-013: Die Arbeitsliste der Auszahlungen.
 *
 * Ueberwiesen wird von Hand; diese Liste sagt, was zu ueberweisen ist, und
 * nimmt die Entscheidung entgegen. Beide Entscheidungen laufen ueber den
 * PayoutService -- Zustandswechsel, Rueckbuchung bei Ablehnung und die Meldung
 * an den Verkaeufer gehoeren zusammen, und das steht dort.
 *
 * Die vollstaendige IBAN wird hier ausgegeben, denn ohne sie ist keine
 * Ueberweisung moeglich. Das ist im gesamten Portal die einzige Stelle: Das
 * Admin-Panel steht ausschliesslich Plattform-Admins offen
 * (User::canAccessPanel), im Verkaeufer-Portal bleibt es bei den letzten vier
 * Stellen.
 */
class PayoutRequestResource extends Resource
{
    protected static ?string $model = PayoutRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 11;

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.marketplace');
    }

    public static function getModelLabel(): string
    {
        return __('marketplace.wallet.admin.payout.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('marketplace.wallet.admin.payout.resource.plural_label');
    }

    /**
     * Offene Anforderungen in der Navigation: Das Geld ist beim Verkaeufer
     * bereits abgebucht, eine uebersehene Zeile ist eine schuldige Zahlung.
     */
    public static function getNavigationBadge(): ?string
    {
        $open = PayoutRequest::query()->open()->count();

        return $open === 0 ? null : (string) $open;
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
            Section::make()
                ->description(__('marketplace.wallet.admin.payout.read_only'))
                ->schema([
                    TextEntry::make('requested_at')
                        ->label(__('marketplace.wallet.admin.payout.fields.requested_at'))
                        ->dateTime(config('app.datetime_format')),
                    TextEntry::make('seller')
                        ->label(__('marketplace.wallet.admin.payout.fields.seller'))
                        ->state(fn (PayoutRequest $record): string => self::sellerName($record)),
                    TextEntry::make('amount_cents')
                        ->label(__('marketplace.wallet.admin.payout.fields.amount'))
                        ->formatStateUsing(fn (int $state): string => WalletResource::money($state)),
                    TextEntry::make('iban')
                        ->label(__('marketplace.wallet.admin.payout.fields.iban'))
                        ->state(fn (PayoutRequest $record): string => self::fullIban($record))
                        ->copyable(),
                    TextEntry::make('status')
                        ->label(__('marketplace.wallet.admin.payout.fields.status'))
                        ->badge()
                        ->formatStateUsing(fn (PayoutStatus $state): string => $state->label()),
                    TextEntry::make('processed_at')
                        ->label(__('marketplace.wallet.admin.payout.fields.processed_at'))
                        ->dateTime(config('app.datetime_format'))
                        ->placeholder('—'),
                    TextEntry::make('processedBy.name')
                        ->label(__('marketplace.wallet.admin.payout.fields.processed_by'))
                        ->placeholder('—'),
                    TextEntry::make('note')
                        ->label(__('marketplace.wallet.admin.payout.fields.note'))
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['wallet.owner', 'processedBy']))
            ->defaultSort('requested_at', 'asc')
            ->emptyStateHeading(__('marketplace.wallet.admin.payout.empty_heading'))
            ->emptyStateDescription(__('marketplace.wallet.admin.payout.empty_description'))
            ->columns([
                TextColumn::make('requested_at')
                    ->label(__('marketplace.wallet.admin.payout.fields.requested_at'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable(),
                TextColumn::make('seller')
                    ->label(__('marketplace.wallet.admin.payout.fields.seller'))
                    ->state(fn (PayoutRequest $record): string => self::sellerName($record)),
                TextColumn::make('amount_cents')
                    ->label(__('marketplace.wallet.admin.payout.fields.amount'))
                    ->formatStateUsing(fn (int $state): string => WalletResource::money($state))
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('iban')
                    ->label(__('marketplace.wallet.admin.payout.fields.iban'))
                    ->state(fn (PayoutRequest $record): string => self::fullIban($record))
                    ->description(fn (PayoutRequest $record): ?string => self::ibanMismatchHint($record))
                    ->copyable(),
                TextColumn::make('status')
                    ->label(__('marketplace.wallet.admin.payout.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (PayoutStatus $state): string => $state->label())
                    ->color(fn (PayoutStatus $state): string => match ($state) {
                        PayoutStatus::REQUESTED => 'warning',
                        PayoutStatus::PAID => 'success',
                        PayoutStatus::REJECTED => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('processedBy.name')
                    ->label(__('marketplace.wallet.admin.payout.fields.processed_by'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('note')
                    ->label(__('marketplace.wallet.admin.payout.fields.note'))
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('marketplace.wallet.admin.payout.fields.status'))
                    ->options(fn (): array => PayoutStatus::options())
                    // Die Liste ist eine Arbeitsliste: Offenes zuerst,
                    // Erledigtes nur auf ausdrueckliche Nachfrage.
                    ->default(PayoutStatus::REQUESTED->value),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('markPaid')
                    ->label(__('marketplace.wallet.admin.payout.actions.mark_paid'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('marketplace.wallet.admin.payout.actions.mark_paid_confirm'))
                    ->schema([
                        Textarea::make('note')
                            ->label(__('marketplace.wallet.admin.payout.fields.note'))
                            ->helperText(__('marketplace.wallet.admin.payout.hints.note_optional'))
                            ->maxLength(1000),
                    ])
                    ->visible(fn (PayoutRequest $record): bool => $record->status->isOpen())
                    ->action(fn (array $data, PayoutRequest $record) => app(PayoutService::class)
                        ->markPaid($record, WalletResource::actingAdmin(), $data['note'] ?? null))
                    ->successNotificationTitle(__('marketplace.wallet.admin.payout.actions.marked_paid')),

                Action::make('reject')
                    ->label(__('marketplace.wallet.admin.payout.actions.reject'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->modalDescription(__('marketplace.wallet.admin.payout.actions.reject_confirm'))
                    ->schema([
                        Textarea::make('note')
                            ->label(__('marketplace.wallet.admin.payout.fields.note'))
                            ->helperText(__('marketplace.wallet.admin.payout.hints.note_required'))
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000),
                    ])
                    ->visible(fn (PayoutRequest $record): bool => $record->status->isOpen())
                    ->action(fn (array $data, PayoutRequest $record) => app(PayoutService::class)
                        ->reject($record, WalletResource::actingAdmin(), $data['note']))
                    ->successNotificationTitle(__('marketplace.wallet.admin.payout.actions.rejected')),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayoutRequests::route('/'),
        ];
    }

    private static function sellerName(PayoutRequest $request): string
    {
        $seller = $request->seller();

        return $seller instanceof Tenant ? $seller->name : '—';
    }

    /**
     * Die vollstaendige IBAN des Verkaeufers. Sie liegt verschluesselt am
     * Mandanten und wird bewusst nicht in der Anforderung mitgespeichert -- ein
     * Beleg ist kein Ort fuer Bankdaten. Gibt es sie nicht mehr, bleibt die
     * gespeicherte letzte Vierergruppe.
     */
    private static function fullIban(PayoutRequest $request): string
    {
        $seller = $request->seller();

        if ($seller instanceof Tenant && $seller->hasPayoutIban()) {
            return (string) $seller->payout_iban;
        }

        return $request->maskedIban();
    }

    /**
     * Hinweis, wenn die heute hinterlegte IBAN nicht die ist, gegen die
     * angefordert wurde. Dann wurde die Bankverbindung nach der Anforderung
     * geaendert -- ueberwiesen wird das erst nach Ruecksprache.
     */
    private static function ibanMismatchHint(PayoutRequest $request): ?string
    {
        $seller = $request->seller();

        if (! $seller instanceof Tenant || ! $seller->hasPayoutIban() || $request->iban_last4 === null) {
            return null;
        }

        return $seller->payout_iban_last4 === $request->iban_last4
            ? null
            : __('marketplace.wallet.admin.payout.iban_changed', ['last4' => $request->iban_last4]);
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
