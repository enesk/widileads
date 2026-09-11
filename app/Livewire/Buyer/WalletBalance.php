<?php

declare(strict_types=1);

namespace App\Livewire\Buyer;

use App\Models\Tenant;
use App\Models\Wallet;
use App\Support\Money;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Der Guthabenstand des Kaeufers (LP-WALLET-011).
 *
 * Zeigt an und bucht nichts. Massgeblich ist `available_cents` -- was fuer
 * laufende Leadkaeufe reserviert ist, steht fuer den naechsten Kauf nicht mehr
 * zur Verfuegung. Der reservierte Teil wird darunter genannt, sonst ist die
 * haeufigste Rueckfrage des Kaeufers, wo sein Geld geblieben ist.
 *
 * Kein Polling: Der Stand aendert sich durch eigene Handlungen des Kaeufers.
 * Wer bucht, wirft `wallet-updated`, und die Komponente zeichnet neu.
 */
class WalletBalance extends Component
{
    /**
     * Schmale Fassung fuer den Seitenkopf des Marktplatzes; ohne Rahmen und
     * ohne Erklaertext.
     */
    #[Locked]
    public bool $compact = false;

    #[On('wallet-updated')]
    public function refreshWallet(): void {}

    public function render(): View
    {
        $wallet = $this->wallet();

        return view('livewire.buyer.wallet-balance', [
            'availableCents' => $wallet->available_cents,
            'reservedCents' => $wallet->reserved_cents,
            'balanceCents' => $wallet->balance_cents,
        ]);
    }

    public function money(int $cents): string
    {
        return Money::format($cents);
    }

    private function wallet(): Wallet
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return Wallet::forBuyer($tenant);
    }
}
