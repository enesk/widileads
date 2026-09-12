<?php

declare(strict_types=1);

namespace App\Events\Wallet;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Die Kaufsperre eines Wallets wurde aufgehoben (LP-POSTPAID-004).
 *
 * Ausgeloest ausschliesslich im WalletService, unmittelbar nach der Buchung,
 * die den Saldo wieder auf null oder darueber gebracht hat -- der Eingang
 * eines Einzugs oder eine Aufladung. Die Aufhebung haengt damit an der
 * Geldbewegung selbst und nicht an einem Job, der sie spaeter nachholt: Ein
 * Kaeufer, der gezahlt hat, soll im selben Moment wieder kaufen koennen.
 *
 * Das Ereignis traegt nur Kennungen, kein Model (wie LeadResolved): Wer das
 * Wallet braucht, laedt es frisch. Ein serialisierter Saldo waere zum
 * Zeitpunkt der Verarbeitung womoeglich veraltet.
 *
 * ShouldDispatchAfterCommit, weil die Aufhebung in der Buchungstransaktion
 * passiert: Ein Listener, der den Kaeufer benachrichtigt, darf erst laufen,
 * wenn die Buchung wirklich in der Datenbank steht.
 */
class WalletUnblocked implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  int  $walletId  Das entsperrte Wallet.
     * @param  int|null  $ownerId  Mandant des Wallets; beim Plattform-Wallet null.
     * @param  int  $balanceCents  Saldo nach der Buchung, die die Sperre aufgehoben hat.
     */
    public function __construct(
        public int $walletId,
        public ?int $ownerId,
        public int $balanceCents,
    ) {}
}
