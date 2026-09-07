<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Resources\Leads\Pages;

use App\Filament\Dashboard\Resources\Leads\LeadResource;
use App\Filament\ListDefaults;
use Filament\Resources\Pages\ListRecords;

/**
 * FB-090: Lead-Liste des Betreibers. Ohne Header-Actions -- Leads entstehen aus
 * Funnel-Einreichungen, nicht von Hand.
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
