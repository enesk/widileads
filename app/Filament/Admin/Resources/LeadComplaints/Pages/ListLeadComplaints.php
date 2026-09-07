<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\LeadComplaints\Pages;

use App\Filament\Admin\Resources\LeadComplaints\LeadComplaintResource;
use App\Filament\ListDefaults;
use Filament\Resources\Pages\ListRecords;

/**
 * FB-058: Pruefliste der Reklamationen -- ohne Header-Actions, da Antraege
 * ausschliesslich von Kaeufern gestellt werden.
 */
class ListLeadComplaints extends ListRecords
{
    use ListDefaults;

    protected static string $resource = LeadComplaintResource::class;

    /**
     * @return array<int, never>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
