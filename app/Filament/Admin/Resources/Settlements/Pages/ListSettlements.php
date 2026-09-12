<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Settlements\Pages;

use App\Filament\Admin\Resources\Settlements\SettlementResource;
use App\Filament\ListDefaults;
use Filament\Resources\Pages\ListRecords;

/**
 * LP-POSTPAID-012: Einzuege ohne Header-Actions -- eine Forderung entsteht am
 * Wallet (Termin, Schwelle oder manueller Einzug), nicht in dieser Liste.
 */
class ListSettlements extends ListRecords
{
    use ListDefaults;

    protected static string $resource = SettlementResource::class;

    /**
     * @return array<int, never>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
