<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\LeadPurchases;

use App\Constants\PurchaseStatus;
use App\Filament\Admin\Resources\LeadPurchases\Pages\ListLeadPurchases;
use App\Filament\Admin\Resources\Wallets\WalletResource;
use App\Models\LeadPurchase;
use App\Services\Wallet\PurchaseService;
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
 * LP-WALLET-013: Die Kaufbelege des Marktplatzes, mit der Erstattung als
 * einziger schreibender Handlung.
 *
 * Der Regelweg einer Erstattung ist die Reklamation des Kaeufers
 * (LeadComplaintResource) -- dort haengt an der Entscheidung auch der
 * Zustandswechsel der Reklamation. Diese Liste ist der Weg daneben: eine
 * Erstattung, die aus Kulanz oder nach einem Support-Gespraech passiert, ohne
 * dass je eine Reklamation gestellt wurde.
 *
 * Erstattet wird ausschliesslich ueber PurchaseService::refund(): Rueckzahlung
 * an den Kaeufer, Rueckbuchung von Einnahme und Provision und der
 * Zustandswechsel gehoeren in eine Transaktion.
 */
class LeadPurchaseResource extends Resource
{
    protected static ?string $model = LeadPurchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 12;

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.marketplace');
    }

    public static function getModelLabel(): string
    {
        return __('marketplace.wallet.admin.purchase.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('marketplace.wallet.admin.purchase.resource.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description(__('marketplace.wallet.admin.purchase.read_only'))
                ->schema([
                    TextEntry::make('purchased_at')
                        ->label(__('marketplace.wallet.admin.purchase.fields.purchased_at'))
                        ->dateTime(config('app.datetime_format')),
                    TextEntry::make('lead_id')
                        ->label(__('marketplace.wallet.admin.purchase.fields.lead'))
                        ->formatStateUsing(fn (int $state): string => '#'.$state),
                    TextEntry::make('buyer.name')
                        ->label(__('marketplace.wallet.admin.purchase.fields.buyer')),
                    TextEntry::make('seller.name')
                        ->label(__('marketplace.wallet.admin.purchase.fields.seller')),
                    TextEntry::make('status')
                        ->label(__('marketplace.wallet.admin.purchase.fields.status'))
                        ->badge()
                        ->formatStateUsing(fn (PurchaseStatus $state): string => self::statusLabel($state)),
                    TextEntry::make('price_cents')
                        ->label(__('marketplace.wallet.admin.purchase.fields.price'))
                        ->formatStateUsing(fn (int $state): string => WalletResource::money($state)),
                    TextEntry::make('commission_cents')
                        ->label(__('marketplace.wallet.admin.purchase.fields.commission'))
                        ->formatStateUsing(fn (LeadPurchase $record): string => WalletResource::money($record->commission_cents)
                            .' ('.$record->commission_percent.' %)'),
                    TextEntry::make('seller_net_cents')
                        ->label(__('marketplace.wallet.admin.purchase.fields.seller_net'))
                        ->formatStateUsing(fn (int $state): string => WalletResource::money($state)),
                    TextEntry::make('captured_at')
                        ->label(__('marketplace.wallet.admin.purchase.fields.captured_at'))
                        ->dateTime(config('app.datetime_format'))
                        ->placeholder('—'),
                    TextEntry::make('released_at')
                        ->label(__('marketplace.wallet.admin.purchase.fields.released_at'))
                        ->dateTime(config('app.datetime_format'))
                        ->placeholder('—'),
                    TextEntry::make('refunded_at')
                        ->label(__('marketplace.wallet.admin.purchase.fields.refunded_at'))
                        ->dateTime(config('app.datetime_format'))
                        ->placeholder('—'),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['buyer', 'seller']))
            ->defaultSort('purchased_at', 'desc')
            ->emptyStateHeading(__('marketplace.wallet.admin.purchase.empty_heading'))
            ->columns([
                TextColumn::make('purchased_at')
                    ->label(__('marketplace.wallet.admin.purchase.fields.purchased_at'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable(),
                TextColumn::make('lead_id')
                    ->label(__('marketplace.wallet.admin.purchase.fields.lead'))
                    ->formatStateUsing(fn (int $state): string => '#'.$state)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('buyer.name')
                    ->label(__('marketplace.wallet.admin.purchase.fields.buyer'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('seller.name')
                    ->label(__('marketplace.wallet.admin.purchase.fields.seller'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('marketplace.wallet.admin.purchase.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (PurchaseStatus $state): string => self::statusLabel($state))
                    ->color(fn (PurchaseStatus $state): string => match ($state) {
                        PurchaseStatus::RESERVED => 'info',
                        PurchaseStatus::CAPTURED => 'success',
                        PurchaseStatus::RELEASED => 'gray',
                        PurchaseStatus::REFUNDED => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('price_cents')
                    ->label(__('marketplace.wallet.admin.purchase.fields.price'))
                    ->formatStateUsing(fn (int $state): string => WalletResource::money($state))
                    ->sortable(),
                TextColumn::make('commission_cents')
                    ->label(__('marketplace.wallet.admin.purchase.fields.commission'))
                    ->formatStateUsing(fn (int $state): string => WalletResource::money($state))
                    ->toggleable(),
                TextColumn::make('seller_net_cents')
                    ->label(__('marketplace.wallet.admin.purchase.fields.seller_net'))
                    ->formatStateUsing(fn (int $state): string => WalletResource::money($state))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('marketplace.wallet.admin.purchase.fields.status'))
                    ->options(fn (): array => self::statusOptions()),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('refund')
                    ->label(__('marketplace.wallet.admin.purchase.actions.refund'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('marketplace.wallet.admin.purchase.actions.refund_confirm'))
                    ->schema([
                        Textarea::make('reason')
                            ->label(__('marketplace.wallet.admin.purchase.fields.reason'))
                            ->helperText(__('marketplace.wallet.admin.purchase.hints.reason'))
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000),
                    ])
                    // Nur abgerechnete Kaeufe: Bei `reserved` ist noch nichts
                    // abgebucht (dann gehoert die Reservierung aufgeloest), bei
                    // `released` und `refunded` hat der Kaeufer sein Geld.
                    ->visible(fn (LeadPurchase $record): bool => $record->status === PurchaseStatus::CAPTURED)
                    ->action(fn (array $data, LeadPurchase $record) => app(PurchaseService::class)
                        ->refund($record, $data['reason'], WalletResource::actingAdmin()))
                    ->successNotificationTitle(__('marketplace.wallet.admin.purchase.actions.refunded')),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeadPurchases::route('/'),
        ];
    }

    public static function statusLabel(PurchaseStatus $status): string
    {
        return __('marketplace.wallet.admin.purchase.status.'.$status->value);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        $options = [];

        foreach (PurchaseStatus::cases() as $case) {
            $options[$case->value] = self::statusLabel($case);
        }

        return $options;
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
