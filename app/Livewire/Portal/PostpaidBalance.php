<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Models\Wallet;
use App\Support\Money;
use App\Support\PostpaidTerms;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Offener Betrag und verbleibender Kreditrahmen eines Postpaid-Kaeufers
 * (LP-POSTPAID-010).
 *
 * Fuer einen Postpaid-Kaeufer ist "Verfuegbar" die falsche Zahl: Sie stuende
 * bei einem Saldo von -87,50 EUR und einem Rahmen von 300 EUR bei 212,50 EUR
 * und sagte nichts darueber, dass 87,50 EUR am Montag abgebucht werden. Dieser
 * Baustein zeigt deshalb beides -- was offen ist und was noch geht.
 *
 * Bei einem Prepaid-Kaeufer rendert er nichts; dort bleibt die gewohnte
 * Guthabenanzeige stehen.
 */
class PostpaidBalance extends Component
{
    use InteractsWithPortalTenant;

    /**
     * Nach Kauf, Aufladung oder Einzug stimmen beide Zahlen nicht mehr.
     */
    #[On('wallet-updated')]
    public function refreshBalance(): void
    {
        // Der Neuaufbau der Ansicht genuegt.
    }

    public function render(): View
    {
        $wallet = Wallet::forBuyer($this->portalTenant());

        if (! PostpaidTerms::isPostpaid($wallet)) {
            return view('livewire.portal.postpaid-balance', ['visible' => false]);
        }

        $limitCents = (int) $wallet->credit_limit_cents;
        $availableCents = max(0, $wallet->available_cents);

        return view('livewire.portal.postpaid-balance', [
            'visible' => true,
            'openAmount' => Money::format($wallet->open_amount_cents),
            'available' => Money::format($availableCents),
            'limit' => Money::format($limitCents),
            // Anteil des Rahmens, der noch frei ist. Ohne Rahmen gibt es
            // nichts auszufuellen -- ein Balken bei 0 % ist dann richtig.
            'usedPercent' => $limitCents > 0
                ? (int) round(max(0, min(100, 100 - ($availableCents / $limitCents * 100))))
                : 100,
            'exhausted' => $availableCents <= 0,
            'tooltip' => PostpaidTerms::settlementTooltip(),
            'settlementsUrl' => route('portal.settlements', ['tenant' => $this->portalTenant()->uuid]),
        ]);
    }
}
