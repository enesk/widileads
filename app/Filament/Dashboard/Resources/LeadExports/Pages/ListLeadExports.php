<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Resources\LeadExports\Pages;

use App\Filament\Dashboard\Resources\LeadExports\LeadExportResource;
use App\Filament\ListDefaults;
use App\Jobs\BuildLeadExport;
use App\Models\LeadExport;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LeadExportBuilder;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

/**
 * Liste der Exporte samt Anforderung (FB-073).
 */
class ListLeadExports extends ListRecords
{
    use ListDefaults;

    protected static string $resource = LeadExportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('request')
                ->label(__('exports.request'))
                ->modalHeading(__('exports.new'))
                ->modalDescription(__('exports.hint'))
                ->modalSubmitActionLabel(__('exports.request'))
                ->schema([
                    CheckboxList::make('columns')
                        ->label(__('exports.columns_legend'))
                        ->options(LeadExportResource::availableColumns())
                        ->default(['id', 'created_at', 'funnel', 'lead_state', 'score', 'name', 'email', 'phone', 'postal_code'])
                        ->required()
                        ->validationMessages(['required' => __('exports.errors.no_columns')])
                        ->columns(3),
                ])
                ->action(fn (array $data) => $this->requestExport((array) $data['columns'])),
        ];
    }

    /**
     * @param  array<int, mixed>  $columns
     */
    private function requestExport(array $columns): void
    {
        $tenant = Filament::getTenant();
        $viewer = auth()->user();

        abort_unless($tenant instanceof Tenant && $viewer instanceof User, 403);

        // Reihenfolge und Umfang gibt der Builder vor -- was er nicht kennt,
        // wird nicht exportiert.
        $selected = array_values(array_intersect(
            LeadExportBuilder::COLUMNS,
            array_map(static fn (mixed $column): string => (string) $column, $columns),
        ));

        if ($selected === []) {
            Notification::make()
                ->warning()
                ->title(__('exports.errors.no_columns'))
                ->send();

            return;
        }

        $export = LeadExport::query()->create([
            'tenant_id' => $tenant->getKey(),
            'requested_by' => $viewer->getKey(),
            'columns' => $selected,
            'filters' => [],
        ]);

        BuildLeadExport::dispatch((int) $export->getKey());
    }
}
