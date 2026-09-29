<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Constants\WalletTransactionType;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Models\LeadPurchase;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Money;
use App\Support\PostpaidTerms;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Transaktionen" im Portal -- das Journal des Kaeufer-Wallets.
 *
 * Zweitfassung von App\Livewire\Buyer\WalletTransactions (LP-WALLET-011) im
 * Portalaufbau. Die Regel dieser Seite bleibt die des Ledgers: **Es wird nichts
 * zusammengefasst und nichts weggelassen.** Eine Ansicht, die Zeilen
 * verschweigt, ergaebe einen Saldo, den ihre sichtbaren Zeilen nicht erklaeren.
 * Gefiltert wird deshalb nur ausdruecklich, und der Filter steht sichtbar ueber
 * der Liste.
 *
 * Zwei Dinge unterscheiden sie von "Bestellungen": Dort steht Geld, das
 * hereinkommt (Aufladungen), hier steht jede Bewegung -- auch die Reservierung
 * beim Leadkauf und ihre Aufloesung.
 *
 * Die Spalte "Saldo danach" ist `balance_after_cents`, also der Kontostand.
 * Eine Reservierung aendert ihn nicht, sie bewegt den reservierten Teil.
 * Deshalb bleiben solche Zeilen farblos und tragen den Hinweis unter der Liste.
 *
 * **Diese Komponente bucht nichts.** Buchungen entstehen ausschliesslich im
 * WalletService.
 */
#[Layout('components.layouts.portal-app')]
class Transactions extends Component
{
    use InteractsWithPortalTenant;

    /** Zeitraumfilter: alle Buchungen. */
    public const PERIOD_ALL = 'all';

    /** So viele Zeilen stehen zuerst da, so viele kommen je Klick dazu. */
    public const PER_PAGE = 20;

    #[Url(as: 'art', except: '')]
    public string $type = '';

    #[Url(as: 'zeitraum', except: self::PERIOD_ALL)]
    public string $period = self::PERIOD_ALL;

    #[Url(as: 'anzahl', except: self::PER_PAGE)]
    public int $visible = self::PER_PAGE;

    public function booted(): void
    {
        abort_unless(Gate::allows('marketplace.access', $this->portalTenant()), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['type', 'period'], true)) {
            $this->visible = self::PER_PAGE;
        }
    }

    public function loadMore(): void
    {
        $this->visible += self::PER_PAGE;
    }

    public function render(): View
    {
        $tenant = $this->portalTenant();
        $wallet = Wallet::forBuyer($tenant);

        $total = $this->matchedQuery($wallet)->count();
        $visible = max(self::PER_PAGE, $this->visible);

        $rows = $this->matchedQuery($wallet)
            ->orderByDesc('created_at')
            // Zweites Merkmal: Reservierung und Abbuchung desselben Kaufs
            // entstehen in derselben Sekunde und stuenden sonst in zufaelliger
            // Reihenfolge.
            ->orderByDesc('id')
            ->limit($visible)
            ->get();

        return view('livewire.portal.transactions', [
            'stats' => $this->stats($wallet),
            'typeOptions' => $this->typeOptions(),
            'periodOptions' => $this->periodOptions(),
            'groups' => $this->groups($rows),
            'shownCount' => $rows->count(),
            'total' => $total,
            'remaining' => max(0, $total - $rows->count()),
            'nextBatch' => min(self::PER_PAGE, max(0, $total - $rows->count())),
            'reservedNote' => __('marketplace.wallet.buyer.history.reserved_note'),
            'surchargeHint' => PostpaidTerms::isPostpaid($wallet) ? PostpaidTerms::surchargeHint() : null,
            'topUpUrl' => route('portal.wallet', ['tenant' => $tenant->uuid]),
            'ordersUrl' => route('portal.orders', ['tenant' => $tenant->uuid]),
        ])->title(__('marketplace.wallet.buyer.history.heading'));
    }

    /**
     * Die drei Zahlen oben.
     *
     * Bei Pay as you go steht vorne der offene Betrag statt des verfuegbaren
     * Guthabens: Der Kaeufer schuldet etwas, und das ist die Zahl, die ihn
     * angeht (LP-POSTPAID-010).
     *
     * "Ausgegeben" zaehlt den gewaehlten Zeitraum, nicht das ganze Journal --
     * sonst stuende ueber einem Zeitraumfilter eine Zahl, die ihn ignoriert.
     *
     * @return array{balance_label: string, balance: string, reserved: string, spent: string, isPostpaid: bool}
     */
    private function stats(Wallet $wallet): array
    {
        $isPostpaid = PostpaidTerms::isPostpaid($wallet);

        $spentCents = (int) $this->periodQuery($wallet)
            ->whereIn('type', [
                WalletTransactionType::CAPTURE->value,
                WalletTransactionType::FEE->value,
            ])
            ->sum('amount_cents');

        return [
            'balance_label' => $isPostpaid
                ? (string) __('marketplace.wallet.buyer.history.portal.stats.open')
                : (string) __('marketplace.wallet.buyer.history.portal.stats.available'),
            'balance' => Money::format($isPostpaid ? $wallet->open_amount_cents : $wallet->available_cents),
            'reserved' => Money::format($wallet->reserved_cents),
            // Abbuchungen stehen im Journal negativ; hier soll der Betrag
            // stehen, nicht seine Richtung.
            'spent' => Money::format(abs($spentCents)),
            'isPostpaid' => $isPostpaid,
        ];
    }

    /**
     * Buchungsarten, die auf einem Kaeufer-Wallet vorkommen. Der Aufschlag
     * fehlt mit Absicht: Er wird auf dem Plattform-Wallet gebucht.
     *
     * @return array<string, string>
     */
    private function typeOptions(): array
    {
        $options = ['' => (string) __('marketplace.wallet.buyer.history.filter.all_types')];

        foreach ([
            WalletTransactionType::TOPUP,
            WalletTransactionType::RESERVE,
            WalletTransactionType::CAPTURE,
            WalletTransactionType::RELEASE,
            WalletTransactionType::REFUND,
            WalletTransactionType::ADJUSTMENT,
            WalletTransactionType::OPENING_BALANCE,
            WalletTransactionType::SETTLEMENT,
            WalletTransactionType::FEE,
        ] as $type) {
            $options[$type->value] = (string) __('marketplace.wallet.types.'.$type->value);
        }

        return $options;
    }

    /**
     * @return array<array-key, string>
     */
    private function periodOptions(): array
    {
        return [
            '30' => (string) __('marketplace.wallet.buyer.history.filter.days', ['days' => 30]),
            '90' => (string) __('marketplace.wallet.buyer.history.filter.days', ['days' => 90]),
            '365' => (string) __('marketplace.wallet.buyer.history.filter.days', ['days' => 365]),
            self::PERIOD_ALL => (string) __('marketplace.wallet.buyer.history.filter.all_time'),
        ];
    }

    /**
     * Die Buchungen nach Monat, neueste Gruppe zuerst.
     *
     * Im Kopf steht die Nettobewegung des angezeigten Ausschnitts, ausdruecklich
     * mit Vorzeichen -- ein Monat, in dem mehr abgebucht als aufgeladen wurde,
     * soll als Minus lesbar sein. Reservierungen zaehlen nicht mit: Sie bewegen
     * den Saldo nicht, und eine Summe, in der sie steckten, ergaebe keinen
     * Kontostand.
     *
     * @param  Collection<int, WalletTransaction>  $rows
     * @return list<array{key: string, title: string, sum: string, rows: list<array<string, mixed>>}>
     */
    private function groups(Collection $rows): array
    {
        $groups = [];

        foreach ($rows as $transaction) {
            $key = $transaction->created_at?->format('Y-m') ?? 'unbekannt';

            $groups[$key]['title'] ??= $transaction->created_at?->translatedFormat('F Y')
                ?? (string) __('marketplace.orders.unknown_month');
            $groups[$key]['sum'] = ($groups[$key]['sum'] ?? 0)
                + ($transaction->type->affectsReservedBalance() ? 0 : $transaction->amount_cents);
            $groups[$key]['rows'][] = $this->row($transaction);
        }

        return array_map(static fn (string $key, array $group): array => [
            'key' => $key,
            'title' => $group['title'],
            'sum' => Money::formatSigned((int) $group['sum']),
            'rows' => $group['rows'],
        ], array_keys($groups), array_values($groups));
    }

    /**
     * Eine Zeile, fertig fuer die Ansicht.
     *
     * @return array<string, mixed>
     */
    private function row(WalletTransaction $transaction): array
    {
        $movesReserved = $transaction->type->affectsReservedBalance();

        return [
            'id' => (int) $transaction->getKey(),
            'date' => $transaction->created_at?->format('d.m., H:i') ?? '-',
            'type' => (string) __('marketplace.wallet.types.'.$transaction->type->value),
            'tone' => $this->tone($transaction),
            'description' => $transaction->description,
            'amount' => Money::formatSigned($transaction->amount_cents),
            // Der Kontostand gilt nur fuer Buchungen, die ihn bewegen. Bei
            // einer Reservierung stuende sonst eine Zahl, die sich nicht
            // geaendert hat, und liesse die Zeile wirkungslos aussehen.
            'balance' => $movesReserved ? null : Money::format($transaction->balance_after_cents),
            'reserved' => $movesReserved ? Money::format($transaction->reserved_after_cents) : null,
            'movesReserved' => $movesReserved,
            'lead' => $this->leadLink($transaction),
        ];
    }

    /**
     * Farbe von Betrag und Abzeichen.
     *
     * Reservierung und Aufloesung bleiben neutral: Die Reservierung traegt ein
     * Plus, weil der reservierte Betrag steigt -- gruen dargestellt liesse sie
     * sich als Gutschrift lesen.
     */
    private function tone(WalletTransaction $transaction): string
    {
        if ($transaction->type->affectsReservedBalance()) {
            return 'neutral';
        }

        return $transaction->amount_cents >= 0 ? 'emerald' : 'zinc';
    }

    /**
     * Der gekaufte Lead hinter einer Buchung, sofern es einen gibt. Ueber ihn
     * findet der Kaeufer Reservierung und Abbuchung als denselben Vorgang
     * wieder.
     *
     * @return array{label: string, url: string}|null
     */
    private function leadLink(WalletTransaction $transaction): ?array
    {
        if ($transaction->reference_type !== LeadPurchase::class || $transaction->reference_id === null) {
            return null;
        }

        $purchase = LeadPurchase::query()
            ->ofBuyer($this->portalTenant())
            ->whereKey($transaction->reference_id)
            ->first();

        if (! $purchase instanceof LeadPurchase) {
            return null;
        }

        return [
            'label' => (string) __('marketplace.wallet.buyer.history.lead_link', ['lead' => $purchase->lead_id]),
            'url' => route('portal.leads.show', [
                'tenant' => $this->portalTenant()->uuid,
                'purchase' => $purchase->getKey(),
            ]),
        ];
    }

    /**
     * Die Buchungen nach Zeitraum und Art.
     *
     * @return Builder<WalletTransaction>
     */
    private function matchedQuery(Wallet $wallet): Builder
    {
        $type = $this->type === '' ? null : WalletTransactionType::tryFrom($this->type);

        return $this->periodQuery($wallet)
            ->when($type !== null, static fn (Builder $query) => $query->where('type', $type?->value));
    }

    /**
     * Die Buchungen nach Zeitraum, ohne Art -- die Grundlage der Kennzahlen.
     *
     * @return Builder<WalletTransaction>
     */
    private function periodQuery(Wallet $wallet): Builder
    {
        $days = match ($this->period) {
            '30' => 30,
            '90' => 90,
            '365' => 365,
            default => null,
        };

        return WalletTransaction::query()
            ->where('wallet_id', $wallet->getKey())
            ->when($days !== null, static fn (Builder $query) => $query->where('created_at', '>=', now()->subDays((int) $days)));
    }
}
