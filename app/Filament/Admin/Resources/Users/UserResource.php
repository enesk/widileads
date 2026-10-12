<?php

namespace App\Filament\Admin\Resources\Users;

use App\Constants\AuditAction;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\RelationManagers\OrdersRelationManager;
use App\Filament\Admin\Resources\Users\RelationManagers\SubscriptionsRelationManager;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Onboarding\OnboardingService;
use App\Services\Phone\PhoneVerificationService;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use STS\FilamentImpersonate\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('User Management');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Users');
    }

    public static function getModelLabel(): string
    {
        return __('User');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->schema([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('public_name')
                        ->required()
                        ->label(__('Public Name'))
                        ->nullable()
                        ->helperText('This is the name that will be displayed publicly (for example in blog posts).')
                        ->maxLength(255),
                    TextInput::make('email')
                        ->email()
                        ->label(__('Email'))
                        ->unique(ignoreRecord: true)
                        ->required()
                        ->maxLength(255),
                    TextInput::make('password')
                        ->password()
                        ->label(__('Password'))
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $context): bool => $context === 'create')
                        ->helperText(fn (string $context): string => ($context !== 'create') ? __('Leave blank to keep the current password.') : '')
                        ->maxLength(255),
                    RichEditor::make('notes')
                        ->nullable()
                        ->label(__('Notes'))
                        ->helperText('Any notes you want to keep about this user.'),
                    Select::make('roles')
                        ->multiple()
                        ->label(__('Roles'))
                        ->relationship('roles', 'name', modifyQueryUsing: function ($query) {
                            return $query->where('is_tenant_role', false);
                        })
                        ->preload(),
                    Checkbox::make('is_admin')
                        ->label(__('Is Admin?'))
                        ->helperText('If checked, this user will be able to access the admin panel. There has to be at least 1 admin user, so if this field is disabled, you will have to create another admin user first before you can disable this one.')
                        // there has to be at least 1 admin user
                        ->disabled(fn (?User $user): bool => $user && $user->is_admin && User::where('is_admin', true)->count() === 1)
                        ->default(false),
                    Checkbox::make('is_blocked')
                        ->label(__('Is Blocked?'))
                        ->disabled(fn (?User $user, string $context): bool => $context === 'create' || $user->is_admin == true)
                        ->helperText('If checked, this user will not be able to log in or use any services provided.')
                        ->default(false),
                ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()->sortable(),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()->sortable(),
                IconColumn::make('email_verified_at')
                    ->label(__('Email Verified'))
                    ->getStateUsing(fn (User $user) => $user->email_verified_at ? true : false)
                    ->boolean(),
                IconColumn::make('phone_number_verified_at')
                    ->label(__('Phone Verified'))
                    ->getStateUsing(fn (User $user) => $user->isPhoneNumberVerified())
                    ->boolean(),
                TextColumn::make('last_seen_at')
                    ->label(__('Last Seen'))
                    ->sortable()
                    ->dateTime(config('app.datetime_format')),
                TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime(config('app.datetime_format')),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->sortable()
                    ->dateTime(config('app.datetime_format')),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Impersonate::make()->redirectTo(route('home')),
                Action::make('resend_verification_email')
                    ->iconButton()
                    ->label(__('Resend Verification Email'))
                    ->icon('heroicon-s-envelope-open')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $record->sendEmailVerificationNotification();

                        Notification::make()
                            ->success()
                            ->body(__('A verification link has been queued to be sent to this user.'))
                            ->send();
                    }),
                Action::make('reset_phone_verification')
                    ->iconButton()
                    ->label(__('Reset Phone Verification'))
                    ->icon('heroicon-s-phone-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('The user will be locked out of the whole application until the phone number is verified again. This is recorded in the audit log.'))
                    ->visible(fn (User $record): bool => $record->isPhoneNumberVerified())
                    ->action(function (User $record) {
                        self::resetPhoneVerification($record);

                        Notification::make()
                            ->success()
                            ->body(__('The phone verification of this user has been reset.'))
                            ->send();
                    }),
                Action::make('release_phone_verification_limit')
                    ->iconButton()
                    ->label(__('Release Verification Limit'))
                    ->icon('heroicon-s-lock-open')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(__('The user can request a new code right away, even if the hourly limit for this phone number is used up. This bypasses a protection against misuse and is recorded in the audit log.'))
                    ->visible(fn (User $record): bool => ! $record->isPhoneNumberVerified()
                        && app(PhoneVerificationService::class)->countingRequestsInWindow($record) > 0)
                    ->action(function (User $record) {
                        self::releasePhoneVerificationLimit($record);
                    }),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])->defaultSort('created_at', 'desc');
    }

    /**
     * Nimmt die Bestaetigung der Rufnummer zurueck (LP-ONBOARD-017).
     *
     * Die Sperre (EnsurePhoneVerified) liest `users.phone_number_verified_at`
     * in jeder Anfrage; der Nutzer ist damit beim naechsten Request gesperrt,
     * ohne Abmelden. Der gespiegelte Schritt `phone_verified` in
     * `tenant_onboarding_steps` bleibt stehen -- er ist nur die Anzeige der
     * Checkliste und nie die Grundlage der Sperre. Verworfen wird nur der
     * gemerkte Fortschritt, sonst zeigte die Checkliste bis zu 30 Sekunden
     * lang noch den alten Haken.
     */
    private static function resetPhoneVerification(User $user): void
    {
        // Der Kanal (SMS/Anruf) gehoert zum zurueckgenommenen Nachweis und
        // darf nicht als Rest stehen bleiben (LP-ONBOARD-004). Die Nummer
        // selbst bleibt: Der Support muss nachher noch sagen koennen, welche
        // bestaetigt war.
        $user->update([
            'phone_number_verified_at' => null,
            'phone_verification_channel' => null,
        ]);

        $workspaces = $user->tenants()->get();

        foreach ($workspaces as $workspace) {
            app(OnboardingService::class)->forgetProgress($workspace);
        }

        app(AuditLogger::class)->log(
            AuditAction::PHONE_VERIFICATION_RESET,
            $user,
            ['workspace_ids' => $workspaces->modelKeys()],
        );
    }

    /**
     * Gibt die Anforderungssperre der Rufnummer frei (LP-ONBOARD-022).
     *
     * Bewusst eine eigene Aktion und kein Anhaengsel am Zuruecksetzen aus
     * LP-ONBOARD-017: Das Zuruecksetzen ist Alltag (falsche Nummer, Konto
     * wechselt den Besitzer), das Umgehen eines Missbrauchsschutzes ist es
     * nicht. Zusammengelegt waere jede Support-Anfrage der bequeme Weg am
     * Limit vorbei, und im Audit-Log stuende nicht mehr, ob das noetig war.
     * Zwei Knoepfe, zwei Eintraege, zwei Entscheidungen.
     */
    private static function releasePhoneVerificationLimit(User $user): void
    {
        $released = app(PhoneVerificationService::class)->releaseRequestLimit($user);

        if ($released === 0) {
            Notification::make()
                ->warning()
                ->body(__('There is no active limit for the phone number of this user.'))
                ->send();

            return;
        }

        app(AuditLogger::class)->log(
            AuditAction::PHONE_VERIFICATION_THROTTLE_RELEASED,
            $user,
            ['released_attempts' => $released],
        );

        Notification::make()
            ->success()
            ->body(__('The user can request a new code right away.'))
            ->send();
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
            SubscriptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return __('Users');
    }
}
