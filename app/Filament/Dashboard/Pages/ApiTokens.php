<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Constants\TenantApiAbility;
use App\Exceptions\TenantApiTokenLimitReachedException;
use App\Models\Tenant;
use App\Services\TenantApiTokenService;
use App\Services\TenantPermissionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Verwaltung der API-Tokens des aktiven Tenants (FB-006, seit FB-090 als
 * Filament-Tabelle).
 *
 * Der Klartext eines Tokens wird genau einmal angezeigt: gespeichert ist nur
 * der Hash, nach dem Ausblenden ist der Klartext nicht wiederherstellbar.
 * Deshalb steht er als Eigenschaft dieser Seite und nicht in einer
 * Benachrichtigung, die beim naechsten Rendern verschwindet.
 */
class ApiTokens extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.dashboard.pages.api-tokens';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedKey;

    /**
     * Der zuletzt erzeugte Token im Klartext - nur fuer die aktuelle Anzeige.
     */
    public ?string $plainTextToken = null;

    public function getHeading(): string|Htmlable
    {
        return __('funnel.api_token.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('funnel.api_token.heading');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('funnel.api_token.nav_label');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return app(TenantPermissionService::class)->tenantUserHasPermissionTo(
            $tenant,
            auth()->user(),
            TenancyPermissionConstants::PERMISSION_MANAGE_API_TOKENS
        );
    }

    public function table(Table $table): Table
    {
        return $table
            // Die Tokens haengen am Mandanten selbst -- eine fremde Zeile kann
            // hier gar nicht erst auftauchen.
            ->query(fn (): Builder => $this->tenant()->tokens()->getQuery())
            ->defaultSort('created_at', 'desc')
            ->description(__('funnel.api_token.description'))
            ->emptyStateHeading(__('funnel.api_token.empty'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('funnel.api_token.name'))
                    ->weight('medium'),
                TextColumn::make('abilities')
                    ->label(__('funnel.api_token.abilities'))
                    ->badge()
                    ->state(fn (PersonalAccessToken $record): array => array_map(
                        static fn (string $ability): string => TenantApiAbility::tryFrom($ability)?->label() ?? $ability,
                        $record->abilities ?? [],
                    )),
                TextColumn::make('last_used_at')
                    ->label(__('funnel.api_token.last_used_at'))
                    ->since()
                    ->placeholder(__('funnel.api_token.never_used')),
                TextColumn::make('created_at')
                    ->label(__('funnel.api_token.created_at'))
                    ->dateTime(config('app.datetime_format', 'd.m.Y H:i'))
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('createToken')
                    ->label(__('funnel.api_token.create'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->modalDescription(__('funnel.api_token.description'))
                    ->modalSubmitActionLabel(__('funnel.api_token.create'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('funnel.api_token.name'))
                            ->placeholder(__('funnel.api_token.name_placeholder'))
                            ->helperText(__('funnel.api_token.name_helper'))
                            ->required()
                            ->maxLength(255),
                        CheckboxList::make('abilities')
                            ->label(__('funnel.api_token.abilities'))
                            ->helperText(__('funnel.api_token.abilities_helper'))
                            ->options(TenantApiAbility::options())
                            ->required()
                            ->rules(['array', 'min:1'])
                            ->nestedRecursiveRules([Rule::in(TenantApiAbility::values())])
                            ->columns(2),
                    ])
                    ->action(fn (array $data) => $this->createToken(
                        (string) $data['name'],
                        array_values((array) $data['abilities']),
                    )),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(__('funnel.api_token.revoke'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->link()
                    ->requiresConfirmation()
                    ->modalDescription(__('funnel.api_token.revoke_confirm'))
                    ->action(fn (PersonalAccessToken $record) => $this->revoke((int) $record->getKey())),
            ]);
    }

    public function dismissPlainTextToken(): void
    {
        $this->plainTextToken = null;
    }

    /**
     * @param  list<string>  $abilities
     */
    private function createToken(string $name, array $abilities): void
    {
        try {
            $token = app(TenantApiTokenService::class)->create($this->tenant(), $name, $abilities);
        } catch (TenantApiTokenLimitReachedException $exception) {
            Notification::make()
                ->danger()
                ->title($exception->getMessage())
                ->send();

            return;
        }

        $this->plainTextToken = $token->plainTextToken;
    }

    private function revoke(int $tokenId): void
    {
        // Ob das Token zu diesem Mandanten gehoert, entscheidet der Service --
        // nicht diese Seite.
        app(TenantApiTokenService::class)->revoke($this->tenant(), $tokenId);
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }
}
