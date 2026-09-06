<?php

declare(strict_types=1);

namespace App\Livewire\Filament\Dashboard;

use App\Constants\TenantApiAbility;
use App\Models\Tenant;
use App\Services\TenantApiTokenService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Component;

/**
 * Seite "API-Zugaenge" im Tenant-Dashboard (FB-006).
 *
 * Der Klartext eines Tokens wird genau einmal angezeigt - nach dem Schliessen
 * des Dialogs ist er nicht wiederherstellbar, gespeichert ist nur der Hash.
 */
class ApiTokens extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    /**
     * Der zuletzt erzeugte Token im Klartext - nur fuer die aktuelle Anzeige.
     */
    public ?string $plainTextToken = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PersonalAccessToken::query()
                ->where('tokenable_type', Tenant::class)
                ->where('tokenable_id', $this->tenant()->id)
            )
            ->heading(__('funnel.api_token.heading'))
            ->description(__('funnel.api_token.description'))
            ->emptyStateHeading(__('funnel.api_token.empty'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('funnel.api_token.name'))
                    ->searchable(),
                TextColumn::make('abilities')
                    ->label(__('funnel.api_token.abilities'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => TenantApiAbility::tryFrom($state)?->label() ?? $state),
                TextColumn::make('last_used_at')
                    ->label(__('funnel.api_token.last_used_at'))
                    ->dateTime()
                    ->placeholder(__('funnel.api_token.never_used'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('funnel.api_token.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label(__('funnel.api_token.create'))
                    ->icon('heroicon-o-plus')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('funnel.api_token.name'))
                            ->helperText(__('funnel.api_token.name_helper'))
                            ->required()
                            ->maxLength(255),
                        CheckboxList::make('abilities')
                            ->label(__('funnel.api_token.abilities'))
                            ->helperText(__('funnel.api_token.abilities_helper'))
                            ->options(TenantApiAbility::options())
                            ->required(),
                    ])
                    ->action(function (array $data, TenantApiTokenService $service): void {
                        $token = $service->create(
                            $this->tenant(),
                            $data['name'],
                            array_values($data['abilities']),
                        );

                        $this->plainTextToken = $token->plainTextToken;

                        Notification::make()
                            ->title(__('funnel.api_token.created'))
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(__('funnel.api_token.revoke'))
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->modalDescription(__('funnel.api_token.revoke_confirm'))
                    ->action(function (PersonalAccessToken $record, TenantApiTokenService $service): void {
                        if (! $service->revoke($this->tenant(), $record->id)) {
                            Notification::make()
                                ->title(__('funnel.api_token.revoke_failed'))
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('funnel.api_token.revoked'))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function dismissPlainTextToken(): void
    {
        $this->plainTextToken = null;
    }

    public function render(): View
    {
        return view('livewire.filament.dashboard.api-tokens');
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }
}
