<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Constants\TenantType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FunnelTemplateImporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * FB-019: Einstieg fuer den Import einer Funnel-Vorlage.
 *
 * Bewusst schlank: eine Aktion mit zwei Auswahlfeldern. Die Arbeit macht
 * FunnelTemplateImporter. Die Funnel-Verwaltung selbst gehoert ins
 * Betreiber-Dashboard (FB-015 ff.), nicht hierher.
 */
class FunnelTemplates extends Page
{
    protected string $view = 'filament.admin.pages.funnel-templates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 13;

    public static function canAccess(): bool
    {
        return auth()->user() instanceof User && (bool) auth()->user()->is_admin;
    }

    public static function getNavigationLabel(): string
    {
        return __('templates.nav_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.funnels');
    }

    public function getTitle(): string
    {
        return __('templates.heading');
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createFromTemplate')
                ->label(__('templates.create.action'))
                ->icon(Heroicon::OutlinedPlus)
                ->modalHeading(__('templates.create.heading'))
                ->modalDescription(__('templates.create.description'))
                ->modalSubmitActionLabel(__('templates.create.submit'))
                ->schema([
                    Select::make('template')
                        ->label(__('templates.create.template'))
                        ->options(fn (): array => $this->templateOptions())
                        ->native(false)
                        ->required(),
                    Select::make('tenant_id')
                        ->label(__('templates.create.tenant'))
                        ->helperText(__('templates.create.tenant_helper'))
                        ->options(fn (): array => $this->operatorTenantOptions())
                        ->searchable()
                        ->native(false)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $tenant = Tenant::query()->findOrFail($data['tenant_id']);

                    $funnel = app(FunnelTemplateImporter::class)
                        ->import((string) $data['template'], $tenant);

                    Notification::make()
                        ->success()
                        ->title(__('templates.create.done', ['name' => $funnel->name]))
                        ->body($funnel->public_token)
                        ->send();
                }),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function templateOptions(): array
    {
        $options = [];

        foreach (app(FunnelTemplateImporter::class)->availableTemplates() as $key) {
            $label = __('templates.names.'.$key);

            $options[$key] = is_string($label) && $label !== 'templates.names.'.$key ? $label : $key;
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private function operatorTenantOptions(): array
    {
        return Tenant::query()
            ->where('type', TenantType::OPERATOR->value)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
