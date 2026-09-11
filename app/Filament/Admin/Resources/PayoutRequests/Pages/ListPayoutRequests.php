<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PayoutRequests\Pages;

use App\Filament\Admin\Resources\PayoutRequests\PayoutRequestResource;
use App\Filament\ListDefaults;
use Filament\Resources\Pages\ListRecords;

/**
 * LP-WALLET-013: Auszahlungs-Queue ohne Header-Actions -- angefordert wird
 * ausschliesslich vom Verkaeufer, hier wird nur entschieden.
 */
class ListPayoutRequests extends ListRecords
{
    use ListDefaults;

    protected static string $resource = PayoutRequestResource::class;

    /**
     * @return array<int, never>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
