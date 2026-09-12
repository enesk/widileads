<?php

declare(strict_types=1);

namespace App\Listeners\Postpaid;

use App\Events\Postpaid\PostpaidDowngradeRequested;
use App\Models\Wallet;
use App\Services\Wallet\PostpaidService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Vollzieht die angeforderte Rueckstufung (LP-POSTPAID-009).
 *
 * Die Stellen, die eine Zahlungsstoerung bemerken -- der Webhook des
 * Zahlungsanbieters, der SettlementService, der PaymentMethodService --,
 * stellen nur fest, dass die Grundlage entfallen ist. Was daraus folgt,
 * entscheidet ausschliesslich dieser Zuhoerer, und er tut es fuer alle Gruende
 * an einer Stelle: Sonst haette jeder Ausloeser seine eigene Vorstellung von
 * Gebuehr und Sperre.
 *
 * Die Gebuehr haengt am Grund und steht in PostpaidService::defaultFeeCentsFor();
 * sie wird hier nicht noch einmal ausgerechnet.
 *
 * **In der Queue, mit drei Versuchen.** Die Rueckstufung bucht eine Gebuehr
 * und verschickt zwei Mails; das gehoert nicht in den HTTP-Request eines
 * Stripe-Webhooks. Die Wiederholung ist gefahrlos: `downgrade()` gibt ein
 * bereits zurueckgestuftes Wallet unveraendert zurueck.
 */
class DowngradePostpaidWallet implements ShouldQueue
{
    /** @var int Ein Deadlock auf der Wallet-Zeile soll sich von selbst erledigen. */
    public int $tries = 3;

    public function __construct(private readonly PostpaidService $postpaid) {}

    public function handle(PostpaidDowngradeRequested $event): void
    {
        $wallet = Wallet::query()->find($event->walletId);

        if (! $wallet instanceof Wallet) {
            Log::warning('Rueckstufung zu unbekanntem Wallet gemeldet.', [
                'wallet_id' => $event->walletId,
                'reason' => $event->reason,
            ]);

            return;
        }

        $this->postpaid->downgrade($wallet, $event->reason);
    }
}
