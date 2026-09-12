<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Constants\SettlementStatus;
use App\Constants\WalletOwnerType;
use App\Constants\WalletTransactionType;
use App\Filament\Admin\Resources\Settlements\SettlementResource;
use App\Filament\Admin\Resources\Wallets\WalletResource;
use App\Models\Settlement;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * LP-POSTPAID-012: Die Risikoseite von Pay as you go auf dem Admin-Dashboard.
 *
 * Vier Zahlen, und jede beantwortet eine eigene Frage: Wie viel Geld ist
 * draussen, wie viel davon ist gerade unterwegs, wo ist es ausgefallen, und
 * was bringt das Verfahren ein. Die ersten drei sind die Bremse, die vierte
 * ist der Grund, es trotzdem anzubieten.
 *
 * Die offenen Forderungen werden aus den Salden gerechnet und nicht aus den
 * Settlements: Ein Kauf ist sofort eine Forderung, das Settlement entsteht
 * erst am Einzugstermin.
 */
class PostpaidStatsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = null;

    /** Zeitraum (Tage), in dem gescheiterte Einzuege gezaehlt werden. */
    private const FAILED_DAYS = 7;

    /**
     * Sichtbar, sobald das Verfahren laeuft oder gelaufen ist. Vier leere
     * Kacheln auf einem Dashboard, das Pay as you go nie eingeschaltet hat,
     * waeren nur Rauschen.
     */
    public static function canView(): bool
    {
        return (bool) config('wallet.postpaid.enabled') || Settlement::query()->exists();
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $openCents = (int) Wallet::query()
            ->where('owner_type', WalletOwnerType::BUYER->value)
            ->where('balance_cents', '<', 0)
            ->sum(DB::raw('-balance_cents'));

        $openWallets = Wallet::query()
            ->where('owner_type', WalletOwnerType::BUYER->value)
            ->where('balance_cents', '<', 0)
            ->count();

        $inFlight = Settlement::query()->whereIn('status', [
            SettlementStatus::PENDING->value,
            SettlementStatus::PROCESSING->value,
            SettlementStatus::RETRY_PENDING->value,
        ]);
        $inFlightCount = (clone $inFlight)->count();
        $inFlightCents = (int) (clone $inFlight)->sum('amount_cents');

        $failedSince = Carbon::now()->subDays(self::FAILED_DAYS);
        $failedCount = Settlement::query()
            ->whereIn('status', [SettlementStatus::FAILED->value, SettlementStatus::RETURNED->value])
            ->where('failed_at', '>=', $failedSince)
            ->count();

        $surchargeCents = (int) WalletTransaction::query()
            ->where('wallet_id', Wallet::forPlatform()->getKey())
            ->where('type', WalletTransactionType::SURCHARGE->value)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('amount_cents');

        return [
            Stat::make(
                __('marketplace.wallet.admin.postpaid.stats.open_receivables'),
                WalletResource::money($openCents),
            )
                ->description(trans_choice('marketplace.wallet.admin.postpaid.stats.open_receivables_hint', $openWallets, ['count' => $openWallets]))
                ->color($openCents === 0 ? 'gray' : 'warning')
                ->url(WalletResource::getUrl('index')),

            Stat::make(
                __('marketplace.wallet.admin.postpaid.stats.in_flight'),
                WalletResource::money($inFlightCents),
            )
                ->description(trans_choice('marketplace.wallet.admin.postpaid.stats.in_flight_hint', $inFlightCount, ['count' => $inFlightCount]))
                ->color($inFlightCount === 0 ? 'gray' : 'info')
                ->url(SettlementResource::getUrl('index')),

            Stat::make(
                __('marketplace.wallet.admin.postpaid.stats.failed'),
                (string) $failedCount,
            )
                ->description(__('marketplace.wallet.admin.postpaid.stats.failed_hint', ['days' => (string) self::FAILED_DAYS]))
                ->color($failedCount === 0 ? 'gray' : 'danger')
                ->url(SettlementResource::getUrl('index')),

            Stat::make(
                __('marketplace.wallet.admin.postpaid.stats.surcharge'),
                WalletResource::money($surchargeCents),
            )
                ->description(__('marketplace.wallet.admin.postpaid.stats.surcharge_hint')),
        ];
    }
}
