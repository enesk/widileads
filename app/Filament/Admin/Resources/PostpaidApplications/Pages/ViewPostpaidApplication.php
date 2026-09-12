<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PostpaidApplications\Pages;

use App\Filament\Admin\Resources\PostpaidApplications\PostpaidApplicationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/**
 * LP-POSTPAID-012: Ein Antrag mit Schnappschuss, Zahlungsmittel und
 * Kaufhistorie -- und den beiden Entscheidungen im Kopf der Seite.
 */
class ViewPostpaidApplication extends ViewRecord
{
    protected static string $resource = PostpaidApplicationResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            PostpaidApplicationResource::approveAction(),
            PostpaidApplicationResource::rejectAction(),
        ];
    }
}
