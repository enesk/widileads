<?php

declare(strict_types=1);

namespace App\Listeners\Onboarding;

use App\Constants\OnboardingStep;
use App\Events\Wallet\WalletToppedUp;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Hakt `first_topup` ab, sobald Guthaben gutgeschrieben ist (LP-ONBOARD-012).
 *
 * Der Zuhoerer entscheidet nichts: Dass die Aufladung gilt, hat
 * CreditWalletAfterPayment festgestellt, und ob der Haken neu ist, entscheidet
 * der Unique-Index im OnboardingService. Die zweite Aufladung aendert deshalb
 * nichts -- weder am Zeitstempel noch an den Ereignissen.
 *
 * In der Queue, weil nichts daran eilt: Die Karte wird beim naechsten
 * Seitenaufbau gelesen, und der Fortschritt haelt ohnehin nur 30 Sekunden.
 * Ein Fehlschlag soll die Gutschrift nicht mitnehmen -- die laeuft bewusst
 * ohne Queue und darf nicht an einer Checkliste scheitern.
 */
class MarkFirstTopup implements ShouldQueue
{
    public function __construct(private readonly OnboardingService $onboarding) {}

    public function handle(WalletToppedUp $event): void
    {
        $this->onboarding->complete(
            $event->buyer,
            OnboardingStep::FIRST_TOPUP,
            null,
            ['wallet_transaction_id' => (int) $event->transaction->getKey()],
        );
    }
}
