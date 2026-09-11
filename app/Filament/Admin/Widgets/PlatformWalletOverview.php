<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Constants\WalletTransactionType;
use App\Filament\Admin\Resources\PayoutRequests\PayoutRequestResource;
use App\Filament\Admin\Resources\Wallets\WalletResource;
use App\Models\PayoutRequest;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * LP-WALLET-013: Die Geldseite des Marktplatzes auf dem Admin-Dashboard.
 *
 * Der Saldo des Plattform-Wallets ist die kumulierte Provision: Jeder
 * abgerechnete Lead bucht seinen Provisionsanteil dorthin, jede Erstattung
 * bucht ihn wieder heraus. Das ist der Betrag, den die Plattform tatsaechlich
 * verdient hat -- und nicht derselbe wie die Summe aller Provisionsbuchungen,
 * sobald einmal erstattet oder korrigiert wurde. Deshalb stehen beide Zahlen
 * hier nebeneinander.
 */
class PlatformWalletOverview extends BaseWidget
{
    protected static ?int $sort = -1;

    protected ?string $pollingInterval = null;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $platform = Wallet::forPlatform();

        $commissionCents = (int) WalletTransaction::query()
            ->where('wallet_id', $platform->getKey())
            ->where('type', WalletTransactionType::COMMISSION->value)
            ->sum('amount_cents');

        $openPayouts = PayoutRequest::query()->open();
        $openPayoutCount = (clone $openPayouts)->count();
        $openPayoutCents = (int) (clone $openPayouts)->sum('amount_cents');

        return [
            Stat::make(
                __('marketplace.wallet.admin.stats.platform_balance'),
                WalletResource::money($platform->balance_cents),
            )
                ->description(__('marketplace.wallet.admin.stats.platform_balance_hint'))
                ->color($platform->balance_cents < 0 ? 'danger' : 'success')
                ->url(WalletResource::getUrl('view', ['record' => $platform])),

            Stat::make(
                __('marketplace.wallet.admin.stats.commission_total'),
                WalletResource::money($commissionCents),
            )
                ->description(__('marketplace.wallet.admin.stats.commission_total_hint')),

            Stat::make(
                __('marketplace.wallet.admin.stats.open_payouts'),
                WalletResource::money($openPayoutCents),
            )
                ->description(trans_choice('marketplace.wallet.admin.stats.open_payouts_hint', $openPayoutCount, ['count' => $openPayoutCount]))
                ->color($openPayoutCount === 0 ? 'gray' : 'warning')
                ->url(PayoutRequestResource::getUrl('index')),
        ];
    }
}
