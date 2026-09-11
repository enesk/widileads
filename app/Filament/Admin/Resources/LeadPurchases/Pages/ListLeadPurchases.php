<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\LeadPurchases\Pages;

use App\Filament\Admin\Resources\LeadPurchases\LeadPurchaseResource;
use App\Filament\ListDefaults;
use Filament\Resources\Pages\ListRecords;

/**
 * LP-WALLET-013: Kaufbelege ohne Header-Actions -- gekauft wird im Marktplatz,
 * hier wird nur nachgesehen und im Ausnahmefall erstattet.
 */
class ListLeadPurchases extends ListRecords
{
    use ListDefaults;

    protected static string $resource = LeadPurchaseResource::class;

    /**
     * @return array<int, never>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
