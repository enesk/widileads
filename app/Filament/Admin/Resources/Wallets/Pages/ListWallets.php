<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Wallets\Pages;

use App\Filament\Admin\Resources\Wallets\WalletResource;
use App\Filament\ListDefaults;
use Filament\Resources\Pages\ListRecords;

/**
 * LP-WALLET-013: Uebersicht aller Wallets, gruppiert nach Typ.
 *
 * Ohne Header-Actions: Ein Wallet entsteht mit seinem Mandanten
 * (TenantObserver), nicht von Hand. Die Korrektur haengt an der Zeile, damit
 * nie offen bleibt, welcher Topf gemeint ist.
 */
class ListWallets extends ListRecords
{
    use ListDefaults;

    protected static string $resource = WalletResource::class;

    /**
     * @return array<int, never>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
