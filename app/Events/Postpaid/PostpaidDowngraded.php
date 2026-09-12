<?php

declare(strict_types=1);

namespace App\Events\Postpaid;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ein Postpaid-Wallet ist zurueckgestuft (LP-POSTPAID-009).
 *
 * Das Gegenstueck zu PostpaidDowngradeRequested: Jenes stellt fest, dass die
 * Grundlage entfallen ist, dieses meldet, dass die Rueckstufung vollzogen ist
 * -- Zahlungsmodus wieder Vorauszahlung, Kreditrahmen 0, gegebenenfalls
 * Kaufsperre und Gebuehr. Ausgeloest ausschliesslich vom PostpaidService.
 *
 * Wer daran haengt, arbeitet mit Folgen und nicht mit der Entscheidung selbst:
 * Anzeige im Portal, Statistik, spaetere Auswertungen. Die Mail an Kaeufer und
 * Betreiber verschickt der Dienst, weil sie zur Rueckstufung gehoert und nicht
 * von einem zusaetzlich registrierten Zuhoerer abhaengen darf.
 *
 * Das Ereignis traegt nur Kennungen und Betraege, keine Modelle: Ein
 * serialisiertes Wallet waere bei spaeterer Verarbeitung veraltet.
 *
 * ShouldDispatchAfterCommit, weil die Rueckstufung in einer Transaktion
 * geschrieben wird.
 */
class PostpaidDowngraded implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  int  $walletId  Das zurueckgestufte Kauf-Wallet.
     * @param  int|null  $ownerId  Mandant des Wallets.
     * @param  string  $reason  Grund, wie er in wallets.postpaid_disabled_reason steht.
     * @param  int  $feeCents  Erhobene Gebuehr (positiv, 0 wenn keine).
     * @param  int  $openAmountCents  Offener Betrag nach der Rueckstufung, Gebuehr eingerechnet.
     * @param  bool  $purchaseBlocked  Ob die Kaufsperre gesetzt wurde.
     */
    public function __construct(
        public int $walletId,
        public ?int $ownerId,
        public string $reason,
        public int $feeCents,
        public int $openAmountCents,
        public bool $purchaseBlocked,
    ) {}
}
