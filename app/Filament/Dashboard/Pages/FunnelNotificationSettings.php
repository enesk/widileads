<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Models\Funnel;
use App\Models\Tenant;
use App\Services\TenantPermissionService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

/**
 * Benachrichtigungsadressen eines Funnels (FB-091).
 *
 * Gepflegt wird eine Liste von Adressen, die eine Mail bekommen, sobald aus
 * diesem Funnel ein Lead entstanden und die Pruefung bestanden ist. Abgelegt
 * wird sie in `funnels.settings`; das Lesen und Saeubern uebernimmt
 * Funnel::notificationEmails().
 *
 * Aufbau bewusst wie FunnelSaleSettings: dieselbe Seitenform, dieselbe
 * Zugangspruefung. Wer Verkaufseinstellungen aendern darf, darf auch
 * entscheiden, wohin die Meldungen gehen.
 */
class FunnelNotificationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.dashboard.pages.funnel-notification-settings';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedBell;

    public Funnel $funnel;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getSlug(?Panel $panel = null): string
    {
        return 'funnels/{funnel}/benachrichtigungen';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(Funnel $funnel): void
    {
        $this->funnel = $funnel;

        $this->getForm('form')?->fill([
            'notification_emails' => $funnel->notificationEmails(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('builder.notifications.section'))
                    ->description(__('builder.notifications.description'))
                    ->schema([
                        TagsInput::make('notification_emails')
                            ->label(__('builder.notifications.emails'))
                            ->helperText(__('builder.notifications.emails_help'))
                            ->placeholder(__('builder.notifications.emails_placeholder'))
                            ->nestedRecursiveRules(['email'])
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = (array) $this->getForm('form')?->getState();

        /** @var array<string, mixed> $settings */
        $settings = $this->funnel->settings ?? [];

        $settings[Funnel::SETTING_NOTIFICATION_EMAILS] = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $address): string => mb_strtolower(trim((string) $address)),
                (array) ($data['notification_emails'] ?? []),
            ),
            static fn (string $address): bool => $address !== '',
        )));

        $this->funnel->update(['settings' => $settings]);

        // Die gesaeuberte Liste zurueck ins Formular: Der Betreiber soll sehen,
        // was tatsaechlich gespeichert wurde, nicht was er getippt hat.
        $this->getForm('form')?->fill([
            'notification_emails' => $this->funnel->refresh()->notificationEmails(),
        ]);

        Notification::make()
            ->title(__('builder.notifications.saved'))
            ->success()
            ->send();
    }

    public function getHeading(): string|Htmlable
    {
        return $this->funnel->name;
    }

    public function getTitle(): string|Htmlable
    {
        return __('builder.notifications.title', ['funnel' => $this->funnel->name]);
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        if (! Gate::allows('funnels.manage', $tenant)) {
            return false;
        }

        return app(TenantPermissionService::class)->tenantUserHasPermissionTo(
            $tenant,
            auth()->user(),
            TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS,
        );
    }
}
