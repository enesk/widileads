<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Resources\Funnels\Pages;

use App\Constants\FunnelStatus;
use App\Filament\Dashboard\Pages\FunnelBuilder;
use App\Filament\Dashboard\Resources\Funnels\FunnelResource;
use App\Filament\ListDefaults;
use App\Models\Tenant;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

/**
 * FB-090: Funnel-Liste. Ein neuer Funnel entsteht mit einem Namen und fuehrt
 * direkt in den Builder -- alles Weitere wird dort gebaut, nicht in einem
 * Formular.
 */
class ListFunnels extends ListRecords
{
    use ListDefaults;

    protected static string $resource = FunnelResource::class;

    /**
     * @return array<int, CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('builder.funnels.create'))
                ->using(function (array $data) {
                    /** @var Tenant $tenant */
                    $tenant = Filament::getTenant();

                    return $tenant->funnels()->create([
                        'name' => $data['name'],
                        'slug' => FunnelResource::freeSlug($tenant, (string) $data['name']),
                        'status' => FunnelStatus::DRAFT,
                    ]);
                })
                ->successRedirectUrl(fn ($record): string => FunnelBuilder::getUrl(['funnel' => $record])),
        ];
    }
}
