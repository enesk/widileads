<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Constants\SaleMode;
use App\Constants\TenancyPermissionConstants;
use App\Models\Funnel;
use App\Models\Tenant;
use App\Services\TenantPermissionService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

/**
 * Verkaufseinstellungen eines Funnels (FB-055a).
 *
 * Ohne diese Seite waren `sale_mode`, `max_buyers` und `shared_price` nur ueber
 * einen Seeder oder direkt am Modell erreichbar -- ein vollstaendig gebautes
 * Ticket ohne Zugang. Der Mehrfachverkauf aus FB-055 haette sich schlicht nicht
 * einschalten lassen.
 *
 * Gebaut mit Filament-Formularkomponenten, nicht mit handgeschriebenem Markup:
 * Neue Oberflaechen im Tenant-Dashboard nutzen Filament, damit sie optisch zum
 * Rest passen.
 */
class FunnelSaleSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.dashboard.pages.funnel-sale-settings';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedBanknotes;

    public Funnel $funnel;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getSlug(?Panel $panel = null): string
    {
        return 'funnels/{funnel}/verkauf';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(Funnel $funnel): void
    {
        $this->funnel = $funnel;

        $this->getForm('form')?->fill([
            'sale_mode' => $funnel->sale_mode->value,
            'max_buyers' => $funnel->max_buyers,
            'shared_price' => $funnel->shared_price,
            'lead_price' => $funnel->lead_price,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('builder.sale.section'))
                    ->description(__('builder.sale.description'))
                    ->schema([
                        Select::make('sale_mode')
                            ->label(__('builder.sale.mode'))
                            ->helperText(__('builder.sale.mode_help'))
                            ->options(SaleMode::options())
                            ->required()
                            ->live(),

                        TextInput::make('lead_price')
                            ->label(__('builder.sale.lead_price'))
                            ->helperText(__('builder.sale.lead_price_help'))
                            ->numeric()
                            ->minValue(0)
                            ->nullable(),

                        // Nur beim Mehrfachverkauf sichtbar: Bei `exclusive`
                        // haben beide Felder keine Wirkung, und ein Feld ohne
                        // Wirkung ist eine Einladung zum Missverstaendnis.
                        TextInput::make('max_buyers')
                            ->label(__('builder.sale.max_buyers'))
                            ->helperText(__('builder.sale.max_buyers_help'))
                            ->numeric()
                            ->minValue(2)
                            ->required()
                            ->visible(fn (Get $get): bool => $get('sale_mode') === SaleMode::SHARED->value),

                        TextInput::make('shared_price')
                            ->label(__('builder.sale.shared_price'))
                            ->helperText(__('builder.sale.shared_price_help'))
                            ->numeric()
                            ->minValue(0)
                            ->nullable()
                            ->visible(fn (Get $get): bool => $get('sale_mode') === SaleMode::SHARED->value),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = (array) $this->getForm('form')?->getState();

        $mode = SaleMode::from((string) $data['sale_mode']);

        $this->funnel->update([
            'sale_mode' => $mode,
            'lead_price' => $data['lead_price'] === '' ? null : $data['lead_price'],
            // Bei `exclusive` bleibt die Hoechstzahl bei eins -- die
            // Verkaufsart entscheidet, nicht die Zahl daneben (FB-055).
            'max_buyers' => $mode->isShared() ? (int) ($data['max_buyers'] ?? 1) : 1,
            'shared_price' => $mode->isShared() && ($data['shared_price'] ?? '') !== ''
                ? $data['shared_price']
                : null,
        ]);

        Notification::make()
            ->title(__('builder.sale.saved'))
            ->success()
            ->send();
    }

    public function getHeading(): string|Htmlable
    {
        return $this->funnel->name;
    }

    public function getTitle(): string|Htmlable
    {
        return __('builder.sale.title', ['funnel' => $this->funnel->name]);
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
