<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Constants\WalletTransactionType;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Models\Tenant;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Guthaben aufladen" im eigenen Portal (Portal Phase 1).
 *
 * Zweitfassung der Filament-Seite App\Filament\Dashboard\Pages\WalletTopUp nach
 * dem Entwurf `guthaben-aufladen.html`. Beide Wege laufen parallel, bis das
 * Portal abgenommen ist.
 *
 * **Diese Komponente bucht nichts.** Das Formular ist ein gewoehnliches
 * POST-Formular an WalletTopupController, der in den vorhandenen
 * Einmalkauf-Checkout uebergibt; gutgeschrieben wird erst nach bestaetigter
 * Zahlung vom Zuhoerer CreditWalletAfterPayment. Genau deshalb hat das Formular
 * kein `wire:model`: Es gibt hier keinen Zwischenzustand, der den Server
 * interessiert, und die Livesumme im Browser (resources/js/modules/topup.js)
 * ist reine Anzeige. Geprueft wird ausschliesslich serverseitig.
 *
 * Betraege, Grenzen und Vorschlaege kommen ausnahmslos aus config('wallet.*') --
 * kein Paket und kein Steuersatz steht im Markup.
 */
#[Layout('components.layouts.portal-app')]
class WalletTopUp extends Component
{
    use InteractsWithPortalTenant;

    public function render(): View
    {
        $tenant = $this->portalTenant();

        abort_unless(Gate::allows('marketplace.access', $tenant), 403);

        $wallet = Wallet::forBuyer($tenant);

        return view('livewire.portal.wallet-top-up', [
            'tenant' => $tenant,
            'balanceCents' => $wallet->balance_cents,
            'minEuro' => $this->minEuro(),
            'maxEuro' => $this->maxEuro(),
            'leadPriceCents' => $this->leadPriceCents(),
            'vatPercent' => (int) config('wallet.topup_vat_percent'),
            'packages' => $this->packages(),
            'workspaces' => $this->workspaces(),
            'recentTopUps' => $this->recentTopUps($wallet),
            'marketplaceUrl' => route('portal.marketplace', ['tenant' => $tenant->uuid]),
            'transactionsUrl' => route('portal.transactions', ['tenant' => $tenant->uuid]),
            'ordersUrl' => route('portal.orders', ['tenant' => $tenant->uuid]),
        ]);
    }

    public function money(int $cents): string
    {
        return Money::format($cents);
    }

    /**
     * Kleinster zulaessiger Betrag in ganzen Euro. Cent-Betraege sind nicht
     * moeglich: Die Menge im Warenkorb des Checkouts ist der Betrag in Euro.
     */
    private function minEuro(): int
    {
        return (int) ceil(((int) config('wallet.topup_min_cents')) / 100);
    }

    private function maxEuro(): int
    {
        return intdiv((int) config('wallet.topup_max_cents'), 100);
    }

    /**
     * Der Preis, an dem "reicht fuer n Leads" gemessen wird. Ein Verkaeufer darf
     * seinen Preis selbst setzen -- diese Zahl ist deshalb ausdruecklich eine
     * Orientierung am Vorgabepreis und keine Zusage.
     */
    private function leadPriceCents(): int
    {
        return max(1, (int) config('wallet.default_lead_price_cents'));
    }

    /**
     * Die Vorschlaege des Entwurfs, aufsteigend und ohne Werte ausserhalb der
     * Spanne -- ein Vorschlag unter dem Mindestbetrag fuehrte nur in die
     * Fehlermeldung des Controllers.
     *
     * Hervorgehoben ist der mittlere Vorschlag ("Beliebt"). Bei zwei
     * Vorschlaegen ist das der zweite, bei einem gibt es keine Hervorhebung.
     *
     * @return list<array{euro: int, leads: int, popular: bool}>
     */
    private function packages(): array
    {
        $euros = array_map(
            static fn (int $cents): int => intdiv($cents, 100),
            array_map('intval', (array) config('wallet.topup_presets_cents', [])),
        );

        $euros = array_values(array_filter(
            array_unique($euros),
            fn (int $euro): bool => $euro >= $this->minEuro() && $euro <= $this->maxEuro(),
        ));

        sort($euros);

        $popular = count($euros) > 1 ? intdiv(count($euros) - 1, 2) : -1;

        return array_values(array_map(
            fn (int $euro, int $index): array => [
                'euro' => $euro,
                'leads' => intdiv($euro * 100, $this->leadPriceCents()),
                'popular' => $index === $popular,
            ],
            $euros,
            array_keys($euros),
        ));
    }

    /**
     * Die Workspaces, fuer die der angemeldete Benutzer aufladen darf. Der
     * Controller prueft dieselbe Mitgliedschaft noch einmal -- diese Liste ist
     * die Bequemlichkeit, nicht der Riegel.
     *
     * @return list<array{uuid: string, name: string}>
     */
    private function workspaces(): array
    {
        $user = $this->portalUser();

        if ($user === null) {
            return [];
        }

        return $user->tenants()
            ->get()
            ->filter(fn (Tenant $tenant): bool => Gate::allows('marketplace.access', $tenant))
            ->map(fn (Tenant $tenant): array => [
                'uuid' => (string) $tenant->uuid,
                'name' => (string) $tenant->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Die letzten Aufladungen dieses Wallets, nur als Gedaechtnisstuetze. Der
     * vollstaendige Verlauf steht im Transaktionsverlauf.
     *
     * @return list<array{date: string, amount: string}>
     */
    private function recentTopUps(Wallet $wallet): array
    {
        return WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', WalletTransactionType::TOPUP->value)
            ->latest('id')
            ->limit(3)
            ->get()
            ->map(fn (WalletTransaction $transaction): array => [
                'date' => $transaction->created_at?->format('d.m.Y') ?? '',
                'amount' => Money::formatSigned($transaction->amount_cents),
            ])
            ->values()
            ->all();
    }
}
