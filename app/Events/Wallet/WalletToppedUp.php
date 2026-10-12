<?php

declare(strict_types=1);

namespace App\Events\Wallet;

use App\Models\Tenant;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Das Guthaben eines Kaeufers ist aufgeladen (LP-WALLET-009).
 *
 * Ausgeloest in App\Listeners\Order\CreditWalletAfterPayment, und zwar genau
 * einmal je Aufladung: Der Zuhoerer feuert nur, wenn die Buchung in diesem
 * Durchlauf neu entstanden ist. Ein wiederholter Webhook des
 * Zahlungsanbieters findet die vorhandene Buchung und loest nichts aus.
 *
 * Nachgetragen fuer LP-ONBOARD-012: Die Aktivierungskarte muss `first_topup`
 * abhaken koennen, ohne das Journal abzufragen. Das Guthaben selbst wird
 * weiterhin ausschliesslich im WalletService gebucht -- dieses Ereignis ist
 * eine Meldung, kein Schreibweg.
 */
class WalletToppedUp
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $buyer,
        public WalletTransaction $transaction,
    ) {}
}
