<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Wallets\Pages;

use App\Filament\Admin\Resources\Wallets\WalletResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/**
 * LP-WALLET-013: Ein Geldtopf mit seinem vollstaendigen Journal.
 *
 * Das Ledger haengt als Relation-Manager darunter; dort steht jede Buchung mit
 * Beleg, Idempotenzschluessel und aufklappbaren Zusatzangaben.
 *
 * Im Kopf stehen die Postpaid-Handlungen (LP-POSTPAID-012). Sie zeigen sich
 * nur, wo sie greifen: Einzug nur bei offenem Betrag ohne laufenden Einzug,
 * Rahmen und Rueckstufung nur bei freigeschaltetem Kauf-Wallet,
 * Wiederfreischaltung nur nach einer Rueckstufung.
 */
class ViewWallet extends ViewRecord
{
    protected static string $resource = WalletResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            WalletResource::settleNowAction(),
            WalletResource::changeCreditLimitAction(),
            WalletResource::reenablePostpaidAction(),
            WalletResource::downgradeAction(),
            WalletResource::adjustAction(),
        ];
    }
}
