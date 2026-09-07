<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BuyerRegistrations;

use App\Constants\BuyerRegistrationStatus;
use App\Filament\Admin\Resources\BuyerRegistrations\Pages\ListBuyerRegistrations;
use App\Models\BuyerRegistration;
use App\Models\User;
use App\Services\BuyerOnboardingService;
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
 * FB-050: Pruefliste der Kaeufer-Registrierungen im Admin-Panel.
 *
 * Der Plattform-Admin entscheidet hier -- und nur hier -- ueber die
 * Freischaltung. Angelegt werden Registrierungen ausschliesslich ueber das
 * oeffentliche Formular, bearbeitet werden sie gar nicht: die kaufmaennischen
 * Angaben sind die Aussage des Kaeufers und bleiben, wie er sie gemacht hat.
 * Zulaessig sind deshalb nur Ansehen, Freischalten und Ablehnen.
 *
 * Beide Entscheidungen laufen ueber den BuyerOnboardingService, damit der
 * Audit-Eintrag nicht davon abhaengt, von wo aus entschieden wurde.
 */
class BuyerRegistrationResource extends Resource
{
    protected static ?string $model = BuyerRegistration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 11;

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.marketplace');
    }

    public static function getModelLabel(): string
    {
        return __('marketplace.buyer.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('marketplace.buyer.resource.plural_label');
    }

    /**
     * Die Zahl der offenen Vorgaenge steht in der Navigation -- eine
     * Registrierung, die niemand bemerkt, ist ein wartender Kunde.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = BuyerRegistration::query()->pending()->count();

        return $pending === 0 ? null : (string) $pending;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description(__('marketplace.buyer.resource.read_only'))
                ->schema([
                    TextEntry::make('company_name')
                        ->label(__('marketplace.buyer.fields.company_name')),
                    TextEntry::make('status')
                        ->label(__('marketplace.buyer.fields.status'))
                        ->badge()
                        ->formatStateUsing(fn (BuyerRegistrationStatus $state): string => $state->label()),
                    TextEntry::make('contact_name')
                        ->label(__('marketplace.buyer.fields.contact_name')),
                    TextEntry::make('contact_email')
                        ->label(__('marketplace.buyer.fields.contact_email')),
                    TextEntry::make('contact_phone')
                        ->label(__('marketplace.buyer.fields.contact_phone'))
                        ->placeholder('-'),
                    TextEntry::make('vat_id')
                        ->label(__('marketplace.buyer.fields.vat_id')),
                    TextEntry::make('broker_register_number')
                        ->label(__('marketplace.buyer.fields.broker_register_number'))
                        ->helperText(__('marketplace.buyer.hints.broker_register_number'))
                        ->placeholder('-'),
                    TextEntry::make('av_accepted_at')
                        ->label(__('marketplace.buyer.fields.av_accepted_at'))
                        ->dateTime(),
                    TextEntry::make('tenant.name')
                        ->label(__('marketplace.buyer.fields.tenant')),
                    TextEntry::make('created_at')
                        ->label(__('marketplace.buyer.fields.created_at'))
                        ->dateTime(),
                    TextEntry::make('reviewer.name')
                        ->label(__('marketplace.buyer.fields.reviewed_by'))
                        ->placeholder('-'),
                    TextEntry::make('reviewed_at')
                        ->label(__('marketplace.buyer.fields.reviewed_at'))
                        ->dateTime()
                        ->placeholder('-'),
                    TextEntry::make('rejection_reason')
                        ->label(__('marketplace.buyer.fields.rejection_reason'))
                        ->placeholder('-')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('marketplace.buyer.resource.empty_heading'))
            ->emptyStateDescription(__('marketplace.buyer.resource.empty_description'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('marketplace.buyer.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('company_name')
                    ->label(__('marketplace.buyer.fields.company_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('marketplace.buyer.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (BuyerRegistrationStatus $state): string => $state->label())
                    ->color(fn (BuyerRegistrationStatus $state): string => match ($state) {
                        BuyerRegistrationStatus::PENDING => 'warning',
                        BuyerRegistrationStatus::ACTIVE => 'success',
                        BuyerRegistrationStatus::REJECTED => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('contact_name')
                    ->label(__('marketplace.buyer.fields.contact_name'))
                    ->searchable(),
                TextColumn::make('contact_email')
                    ->label(__('marketplace.buyer.fields.contact_email'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('vat_id')
                    ->label(__('marketplace.buyer.fields.vat_id'))
                    ->toggleable(),
                TextColumn::make('broker_register_number')
                    ->label(__('marketplace.buyer.fields.broker_register_number'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('marketplace.buyer.fields.status'))
                    ->options(fn (): array => BuyerRegistrationStatus::options()),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('approve')
                    ->label(__('marketplace.buyer.actions.approve'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('marketplace.buyer.actions.approve_confirm'))
                    ->visible(fn (BuyerRegistration $record): bool => ! $record->isApproved())
                    ->action(fn (BuyerRegistration $record) => app(BuyerOnboardingService::class)
                        ->approve($record, self::actingAdmin()))
                    ->successNotificationTitle(__('marketplace.buyer.actions.approved')),

                Action::make('reject')
                    ->label(__('marketplace.buyer.actions.reject'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->schema([
                        Textarea::make('reason')
                            ->label(__('marketplace.buyer.fields.rejection_reason'))
                            ->helperText(__('marketplace.buyer.hints.rejection_reason'))
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000),
                    ])
                    ->visible(fn (BuyerRegistration $record): bool => $record->status !== BuyerRegistrationStatus::REJECTED)
                    ->action(fn (array $data, BuyerRegistration $record) => app(BuyerOnboardingService::class)
                        ->reject($record, self::actingAdmin(), $data['reason']))
                    ->successNotificationTitle(__('marketplace.buyer.actions.rejected')),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBuyerRegistrations::route('/'),
        ];
    }

    /**
     * Registrierungen entstehen ausschliesslich ueber das oeffentliche
     * Formular -- ein von Hand angelegter Kaeufer haette nie zugestimmt.
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

    private static function actingAdmin(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
