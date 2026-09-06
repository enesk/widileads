<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Leads\Pages;

use App\Filament\Admin\Resources\Leads\LeadResource;
use App\Filament\ListDefaults;
use Filament\Resources\Pages\ListRecords;

/**
 * FB-036: Lead-Uebersicht im Admin-Panel. Ohne Header-Actions -- Leads
 * entstehen aus Funnel-Einreichungen, nicht von Hand.
 */
class ListLeads extends ListRecords
{
    use ListDefaults;

    protected static string $resource = LeadResource::class;

    /**
     * @return array<int, never>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
