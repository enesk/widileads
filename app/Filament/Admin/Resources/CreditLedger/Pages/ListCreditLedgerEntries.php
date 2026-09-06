<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CreditLedger\Pages;

use App\Filament\Admin\Resources\CreditLedger\CreditLedgerResource;
use App\Filament\ListDefaults;
use App\Models\Tenant;
use App\Services\CreditLedgerService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

/**
 * FB-052: Uebersicht des Guthabenkontos.
 *
 * Einzige schreibende Handlung ist die manuelle Korrektur -- zugleich der Weg
 * fuer Guthaben auf Rechnung. Sie laeuft ueber den CreditLedgerService, damit
 * Vorzeichen und Deckung auch hier geprueft werden.
 */
class ListCreditLedgerEntries extends ListRecords
{
    use ListDefaults;

    protected static string $resource = CreditLedgerResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('adjust')
                ->label(__('marketplace.credit.actions.adjust'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->modalDescription(__('marketplace.credit.actions.adjust_description'))
                ->schema(CreditLedgerResource::adjustmentFormSchema())
                ->action(function (array $data): void {
                    $tenant = Tenant::query()->findOrFail($data['tenant_id']);

                    app(CreditLedgerService::class)->adjust(
                        $tenant,
                        (int) $data['credits'],
                        ($data['amount_cents'] ?? null) === null ? null : (int) $data['amount_cents'],
                    );
                })
                ->successNotificationTitle(__('marketplace.credit.actions.adjusted')),
        ];
    }
}
