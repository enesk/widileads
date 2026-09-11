<?php

declare(strict_types=1);

namespace App\Livewire\Seller;

use App\Exceptions\PayoutNotAllowedException;
use App\Models\PayoutRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\PayoutService;
use App\Support\Money;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Auszahlung anfordern und bisherige Anforderungen (LP-WALLET-012).
 *
 * Die Komponente entscheidet nichts: Ob eine Auszahlung zulaessig ist, prueft
 * ausschliesslich der PayoutService -- IBAN, Mindestbetrag und Deckung. Was
 * hier steht, ist die Vorschau derselben Regeln, damit der Verkaeufer nicht
 * erst am Fehler merkt, dass er noch keine Bankverbindung hinterlegt hat.
 *
 * Der Betrag verlaesst das Wallet sofort mit der Anforderung, nicht erst mit
 * der Ueberweisung (PayoutService). Deshalb der Bestaetigungsdialog: Was hier
 * geklickt wird, ist bereits die Geldbewegung.
 *
 * Die vollstaendige IBAN wird nie angezeigt, nur ihre letzte Vierergruppe --
 * sie steht verschluesselt am Mandanten und hat in keiner Oberflaeche etwas
 * verloren.
 */
class PayoutRequestForm extends Component
{
    /** Anzahl der angezeigten bisherigen Anforderungen. */
    private const HISTORY_LIMIT = 10;

    public string $amountEuro = '';

    public function mount(): void
    {
        $this->amountEuro = $this->defaultAmountEuro();
    }

    /**
     * Auszahlung anfordern. Gebucht wird im PayoutService; hier wird nur
     * uebersetzt, was er zurueckmeldet.
     */
    public function requestPayout(PayoutService $payouts): void
    {
        $cents = $this->centsOf($this->amountEuro);

        if ($cents === null) {
            Notification::make()
                ->title(__('marketplace.wallet.seller.payout.invalid_amount'))
                ->danger()
                ->send();

            return;
        }

        try {
            $payout = $payouts->request($this->currentTenant(), $cents, $this->currentUser());
        } catch (PayoutNotAllowedException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('marketplace.wallet.seller.payout.requested', [
                'amount' => $this->money((int) $payout->amount_cents),
            ]))
            ->success()
            ->send();

        $this->amountEuro = $this->defaultAmountEuro();

        // Saldo und Verlauf daneben stimmen jetzt nicht mehr.
        $this->dispatch('wallet-updated');
    }

    public function render(): View
    {
        return view('livewire.seller.payout-request-form', [
            'availableCents' => $this->availableCents(),
            'minimumCents' => $this->minimumCents(),
            'hasIban' => $this->currentTenant()->hasPayoutIban(),
            'ibanLast4' => $this->currentTenant()->payout_iban_last4,
            'canRequest' => $this->canRequest(),
            'requests' => $this->requests(),
        ]);
    }

    /**
     * Darf jetzt angefordert werden? Dieselben Bedingungen, die der
     * PayoutService prueft.
     */
    public function canRequest(): bool
    {
        return $this->currentTenant()->hasPayoutIban()
            && $this->availableCents() >= $this->minimumCents();
    }

    public function money(int $cents): string
    {
        return Money::format($cents);
    }

    /**
     * Freies Guthaben des Verkaeufer-Wallets.
     */
    private function availableCents(): int
    {
        return Wallet::forSeller($this->currentTenant())->available_cents;
    }

    private function minimumCents(): int
    {
        return (int) config('wallet.payout_min_cents');
    }

    /**
     * Vorbelegung: das volle Guthaben. Der haeufigste Fall ist die Auszahlung
     * von allem, was dasteht.
     */
    private function defaultAmountEuro(): string
    {
        $available = $this->availableCents();

        return $available <= 0 ? '' : Money::decimal($available);
    }

    /**
     * @return Collection<int, PayoutRequest>
     */
    private function requests(): Collection
    {
        return PayoutRequest::query()
            ->forWallet(Wallet::forSeller($this->currentTenant()))
            ->limit(self::HISTORY_LIMIT)
            ->get();
    }

    /**
     * Eingabe in Euro zu Cent. Null, wenn nichts Brauchbares dasteht.
     */
    private function centsOf(string $input): ?int
    {
        $value = str_replace(',', '.', trim($input));

        if ($value === '' || ! is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        return (int) round(((float) $value) * 100);
    }

    private function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private function currentTenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }
}
