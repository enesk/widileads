<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Constants\WalletTransactionType;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Models\Tenant;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Money;
use App\Support\TopupPackages;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
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

    /**
     * Vorbelegter Betrag in ganzen Euro, aus der Adresse.
     *
     * Gesetzt wird er vom Band des gesperrten Kontos (LP-POSTPAID-010): Dort
     * steht der offene Betrag, und der Knopf daneben soll den Kaeufer nicht
     * bloss auf diese Seite bringen, sondern mit dem Betrag, der ihn
     * entsperrt. Ein freier Betrag bleibt es trotzdem -- die Zahl steht im
     * Eingabefeld und ist aenderbar.
     */
    #[Url(as: 'betrag', except: '')]
    public string $prefill = '';

    /**
     * Kommt der Kaeufer aus dem Einrichtungs-Wizard (LP-ONBOARD-010)?
     *
     * Dann aendern sich genau zwei Dinge: Der Rueckweg oben fuehrt in den
     * Wizard-Schritt statt in den Marktplatz, und das Formular schickt das
     * Kennzeichen mit, damit die Erfolgsseite den Kaeufer wieder in die
     * Einrichtung bringt (LP-PORTAL-015). Gerechnet oder gebucht wird
     * deswegen nichts anders.
     */
    #[Url(as: 'onboarding', except: '')]
    public string $onboarding = '';

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
            'prefillEuro' => $this->prefillEuro(),
            'isOnboarding' => $this->isOnboarding(),
            'backUrl' => $this->isOnboarding()
                ? route('portal.onboarding', ['tenant' => $tenant->uuid, 'step' => 'payment'])
                : route('portal.marketplace', ['tenant' => $tenant->uuid]),
            'backLabel' => $this->isOnboarding()
                ? __('portal.topup.back_onboarding')
                : __('portal.topup.back'),
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
     * Ein Kennzeichen aus der Adresse, nichts weiter -- es entscheidet ueber
     * zwei Verweise und nicht ueber Geld.
     */
    private function isOnboarding(): bool
    {
        return $this->onboarding === '1';
    }

    /**
     * Der vorbelegte Betrag, auf die zulaessige Spanne gestutzt -- 0, wenn
     * keiner mitgegeben wurde. Die Adresse kommt vom Browser und ist damit
     * kein Beleg; geprueft wird beim Absenden ohnehin noch einmal im
     * WalletTopupController.
     */
    private function prefillEuro(): int
    {
        if (! is_numeric($this->prefill)) {
            return 0;
        }

        $euro = (int) $this->prefill;

        return $euro < $this->minEuro() || $euro > $this->maxEuro() ? 0 : $euro;
    }

    /**
     * Kleinster zulaessiger Betrag in ganzen Euro. Cent-Betraege sind nicht
     * moeglich: Die Menge im Warenkorb des Checkouts ist der Betrag in Euro.
     */
    private function minEuro(): int
    {
        return TopupPackages::minEuro();
    }

    private function maxEuro(): int
    {
        return TopupPackages::maxEuro();
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
     * Die Vorschlaege des Entwurfs. Die Regel dahinter steht in
     * App\Support\TopupPackages -- derselbe Code baut die Karten des
     * Zahlungsschritts im Wizard (LP-ONBOARD-010).
     *
     * @return list<array{euro: int, leads: int, popular: bool}>
     */
    private function packages(): array
    {
        return TopupPackages::forLeadPrice($this->leadPriceCents());
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
