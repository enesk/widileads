<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PostpaidApplications\Pages;

use App\Filament\Admin\Resources\PostpaidApplications\PostpaidApplicationResource;
use App\Filament\ListDefaults;
use Filament\Resources\Pages\ListRecords;

/**
 * LP-POSTPAID-012: Antrags-Queue ohne Header-Actions -- beantragt wird
 * ausschliesslich vom Kaeufer im Portal, hier wird nur entschieden.
 */
class ListPostpaidApplications extends ListRecords
{
    use ListDefaults;

    protected static string $resource = PostpaidApplicationResource::class;

    /**
     * @return array<int, never>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
