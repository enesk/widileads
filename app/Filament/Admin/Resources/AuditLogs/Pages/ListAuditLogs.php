<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AuditLogs\Pages;

use App\Filament\Admin\Resources\AuditLogs\AuditLogResource;
use App\Filament\ListDefaults;
use Filament\Resources\Pages\ListRecords;

/**
 * FB-005: Uebersicht des Audit-Logs -- ohne Header-Actions, da Eintraege weder
 * angelegt noch veraendert werden koennen.
 */
class ListAuditLogs extends ListRecords
{
    use ListDefaults;

    protected static string $resource = AuditLogResource::class;

    /**
     * @return array<int, never>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
