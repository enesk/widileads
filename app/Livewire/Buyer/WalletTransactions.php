<?php

declare(strict_types=1);

namespace App\Livewire\Buyer;

use App\Constants\WalletTransactionType;
use App\Filament\Dashboard\Pages\PurchasedLeadDetail;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Money;
use Filament\Facades\Filament;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Der Transaktionsverlauf des Kaeufer-Wallets (LP-WALLET-011).
 *
 * Das Journal, so wie es im Ledger steht: Jede Zeile ist eine Buchung, nichts
 * wird zusammengefasst und nichts weggelassen. Eine gefilterte Sicht, die
 * Zeilen verschweigt, ergaebe einen Saldo, den seine sichtbaren Zeilen nicht
 * erklaeren -- deshalb filtert der Kaeufer selbst, und der Filter steht sichtbar
 * ueber der Tabelle.
 *
 * Reservierung und ihre Aufloesung gehoeren zusammen: Beide Buchungen tragen
 * denselben Kaufbeleg als Referenz und verlinken deshalb auf denselben Lead.
 *
 * Die Spalte "Saldo danach" ist `balance_after_cents`, also der Kontostand.
 * Eine Reservierung aendert ihn nicht -- sie bewegt den reservierten Teil. Das
 * steht als Hinweis unter der Tabelle, statt zwei Saldospalten zu zeigen, die
 * auf einem Telefon ohnehin niemand nebeneinander liest.
 */
class WalletTransactions extends Component
{
    use WithPagination;

    /** Zeitraumfilter: alle Buchungen. */
    public const PERIOD_ALL = 'all';

    private const PER_PAGE = 20;

    #[Url(as: 'art', except: '')]
    public string $type = '';

    #[Url(as: 'zeitraum', except: self::PERIOD_ALL)]
    public string $period = self::PERIOD_ALL;

    /**
     * Nach einer eigenen Buchung stimmt die erste Seite nicht mehr.
     */
    #[On('wallet-updated')]
    public function refreshTransactions(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.buyer.wallet-transactions', [
            'transactions' => $this->transactions(),
        ]);
    }

    /**
     * Buchungsarten, die auf einem Kaeufer-Wallet vorkommen koennen, als
     * Auswahl fuer den Filter.
     *
     * @return array<string, string>
     */
    public function typeOptions(): array
    {
        $options = ['' => (string) __('marketplace.wallet.buyer.history.filter.all_types')];

        foreach (self::buyerTypes() as $type) {
            $options[$type->value] = (string) __('marketplace.wallet.types.'.$type->value);
        }

        return $options;
    }

    /**
     * Die Schluessel '30', '90' und '365' macht PHP zu Ganzzahlen -- im
     * Markup stehen sie wieder als Text und treffen deshalb auf $period.
     *
     * @return array<array-key, string>
     */
    public function periodOptions(): array
    {
        return [
            '30' => (string) __('marketplace.wallet.buyer.history.filter.days', ['days' => 30]),
            '90' => (string) __('marketplace.wallet.buyer.history.filter.days', ['days' => 90]),
            '365' => (string) __('marketplace.wallet.buyer.history.filter.days', ['days' => 365]),
            self::PERIOD_ALL => (string) __('marketplace.wallet.buyer.history.filter.all_time'),
        ];
    }

    public function money(int $cents): string
    {
        return Money::format($cents);
    }

    public function signedMoney(int $cents): string
    {
        return Money::formatSigned($cents);
    }

    /**
     * Bewegt diese Buchung den reservierten Betrag statt des Saldos?
     *
     * Die Unterscheidung ist fuer die Anzeige wesentlich: Eine Reservierung
     * traegt ein Plus, weil der reservierte Betrag steigt -- gruen und mit Plus
     * dargestellt liesse sie sich als Gutschrift lesen. Solche Zeilen bleiben
     * deshalb neutral, die Bedeutung traegt das Abzeichen.
     */
    public function movesReserved(WalletTransaction $transaction): bool
    {
        return $transaction->type->affectsReservedBalance();
    }

    /**
     * Farbe des Abzeichens einer Buchungsart. Gutschriften gruen, Abbuchungen
     * grau, Reservierungen gelb -- geblockt ist weder das eine noch das andere.
     */
    public function badgeColor(WalletTransactionType $type): string
    {
        return match ($type) {
            WalletTransactionType::TOPUP, WalletTransactionType::REFUND => 'success',
            WalletTransactionType::RESERVE => 'warning',
            WalletTransactionType::RELEASE => 'info',
            WalletTransactionType::ADJUSTMENT => 'gray',
            default => 'gray',
        };
    }

    /**
     * Der gekaufte Lead hinter einer Buchung, sofern es einen gibt. Ueber ihn
     * findet der Kaeufer die Reservierung und ihre Aufloesung als denselben
     * Vorgang wieder.
     *
     * @return array{label: string, url: string}|null
     */
    public function leadLink(WalletTransaction $transaction): ?array
    {
        if ($transaction->reference_type !== LeadPurchase::class || $transaction->reference_id === null) {
            return null;
        }

        $purchase = LeadPurchase::query()
            ->ofBuyer($this->tenant())
            ->whereKey($transaction->reference_id)
            ->first();

        if (! $purchase instanceof LeadPurchase) {
            return null;
        }

        return [
            'label' => __('marketplace.wallet.buyer.history.lead_link', ['lead' => $purchase->lead_id]),
            'url' => PurchasedLeadDetail::getUrl(['purchase' => $purchase->getKey()]),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, WalletTransaction>
     */
    private function transactions(): LengthAwarePaginator
    {
        return WalletTransaction::query()
            ->where('wallet_id', $this->wallet()->getKey())
            ->when($this->selectedType() !== null, fn (Builder $query) => $query->where('type', $this->selectedType()?->value))
            ->when($this->sinceDays() !== null, fn (Builder $query) => $query->where('created_at', '>=', now()->subDays((int) $this->sinceDays())))
            ->orderByDesc('created_at')
            // Zweites Sortiermerkmal: Reservierung und Abbuchung desselben
            // Kaufs entstehen in derselben Sekunde und stuenden sonst in
            // zufaelliger Reihenfolge.
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }

    private function selectedType(): ?WalletTransactionType
    {
        return $this->type === '' ? null : WalletTransactionType::tryFrom($this->type);
    }

    private function sinceDays(): ?int
    {
        return match ($this->period) {
            '30' => 30,
            '90' => 90,
            '365' => 365,
            default => null,
        };
    }

    /**
     * @return list<WalletTransactionType>
     */
    private static function buyerTypes(): array
    {
        return [
            WalletTransactionType::TOPUP,
            WalletTransactionType::RESERVE,
            WalletTransactionType::CAPTURE,
            WalletTransactionType::RELEASE,
            WalletTransactionType::REFUND,
            WalletTransactionType::ADJUSTMENT,
            WalletTransactionType::OPENING_BALANCE,
        ];
    }

    private function wallet(): Wallet
    {
        return Wallet::forBuyer($this->tenant());
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }
}
