<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BuyerRegistrations\Pages;

use App\Filament\Admin\Resources\BuyerRegistrations\BuyerRegistrationResource;
use App\Filament\ListDefaults;
use Filament\Resources\Pages\ListRecords;

/**
 * FB-050: Pruefliste der Kaeufer-Registrierungen -- ohne Header-Actions, da
 * Registrierungen ausschliesslich ueber das oeffentliche Formular entstehen.
 */
class ListBuyerRegistrations extends ListRecords
{
    use ListDefaults;

    protected static string $resource = BuyerRegistrationResource::class;

    /**
     * @return array<int, never>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
