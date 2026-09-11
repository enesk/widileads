<?php

declare(strict_types=1);

namespace App\Livewire\Seller;

use App\Constants\PurchaseStatus;
use App\Constants\WalletTransactionType;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Money;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Einnahmen und Buchungen des Verkaeufers (LP-WALLET-012).
 *
 * Die Komponente zeigt nur an und bucht nichts. Die Salden kommen vom Wallet,
 * der Verlauf aus dem Journal -- gerechnet wird hier nichts, was nicht
 * ohnehin im Ledger steht.
 *
 * Drei Zahlen, die nicht verwechselt werden duerfen:
 *
 * - **Auszahlungsguthaben** ist `available_cents` des Verkaeufer-Wallets. Nur
 *   dieser Betrag ist Geld.
 * - **In Reservierung** ist die Summe der Netto-Anteile aus Kaeufen, die noch
 *   auf ihre Abrechnung warten (PurchaseStatus::RESERVED). Das ist eine
 *   Prognose und steht in keinem Wallet: Wird der Kauf aufgeloest, faellt sie
 *   ersatzlos weg. Sie wird deshalb als ausstehend gekennzeichnet und nie zum
 *   Guthaben addiert.
 * - **Einnahmen der letzten 30 Tage** ist die Summe der `earning`-Buchungen
 *   dieses Zeitraums, mit Vorzeichen: Die Rueckbuchung einer erstatteten
 *   Einnahme ist eine negative `earning`-Zeile und mindert die Summe.
 *
 * Der Verlauf zeigt alle Buchungen des Wallets, nicht nur `earning`, `payout`
 * und `adjustment`. Auf einem Verkaeufer-Wallet gibt es praktisch keine
 * anderen; eine Zeile aber wegzufiltern -- etwa den Eroeffnungssaldo aus der
 * Credit-Migration -- hiesse, einen Saldo zu zeigen, den seine sichtbaren
 * Zeilen nicht ergeben.
 *
 * Statt einer Seitenblaetterung waechst die Liste auf Knopfdruck. Ein Journal
 * wird von oben gelesen; wer weiter zurueck will, klickt weiter.
 */
class SellerWallet extends Component
{
    /** Zeilen je Schritt im Verlauf. */
    private const PAGE_SIZE = 15;

    /** Tage, ueber die die Einnahmen zusammengefasst werden. */
    private const EARNINGS_WINDOW_DAYS = 30;

    public int $visible = self::PAGE_SIZE;

    /**
     * Nach einer Auszahlungsanforderung stimmen Saldo und Verlauf nicht mehr.
     * Das Neuzeichnen genuegt -- die Zahlen werden bei jedem Rendern frisch
     * gelesen.
     */
    #[On('wallet-updated')]
    public function refreshWallet(): void {}

    public function showMore(): void
    {
        $this->visible += self::PAGE_SIZE;
    }

    public function render(): View
    {
        $wallet = $this->wallet();

        return view('livewire.seller.seller-wallet', [
            'availableCents' => $wallet->available_cents,
            'pendingCents' => $this->pendingCents(),
            'earningsCents' => $this->earningsCents(),
            'earningsDays' => self::EARNINGS_WINDOW_DAYS,
            'transactions' => $this->transactions($wallet),
            'hasMore' => $this->hasMore($wallet),
        ]);
    }

    /**
     * Netto-Anteil aller Kaeufe, deren Geldseite noch offen ist. Prognose,
     * kein Guthaben.
     */
    public function pendingCents(): int
    {
        return (int) LeadPurchase::query()
            ->where('seller_tenant_id', $this->currentTenant()->getKey())
            ->where('status', PurchaseStatus::RESERVED->value)
            ->sum('seller_net_cents');
    }

    /**
     * Einnahmen des Betrachtungszeitraums, mit Vorzeichen.
     */
    public function earningsCents(): int
    {
        return (int) WalletTransaction::query()
            ->where('wallet_id', $this->wallet()->getKey())
            ->where('type', WalletTransactionType::EARNING->value)
            ->where('created_at', '>=', now()->subDays(self::EARNINGS_WINDOW_DAYS))
            ->sum('amount_cents');
    }

    public function money(int $cents): string
    {
        return Money::format($cents);
    }

    /**
     * Betrag mit ausdruecklichem Vorzeichen -- im Journal ist es die halbe
     * Aussage.
     */
    public function signedMoney(int $cents): string
    {
        return Money::formatSigned($cents);
    }

    /**
     * Das Verkaufs-Wallet des aktiven Workspaces.
     */
    public function wallet(): Wallet
    {
        return Wallet::forSeller($this->currentTenant());
    }

    /**
     * @return Collection<int, WalletTransaction>
     */
    private function transactions(Wallet $wallet): Collection
    {
        return WalletTransaction::query()
            ->where('wallet_id', $wallet->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($this->visible)
            ->get();
    }

    private function hasMore(Wallet $wallet): bool
    {
        return WalletTransaction::query()
            ->where('wallet_id', $wallet->getKey())
            ->count() > $this->visible;
    }

    private function currentTenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }
}
