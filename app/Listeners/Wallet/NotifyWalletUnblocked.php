<?php

declare(strict_types=1);

namespace App\Listeners\Wallet;

use App\Events\Wallet\WalletUnblocked;
use App\Mail\Wallet\WalletUnblockedMail;
use App\Models\Tenant;
use App\Models\Wallet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sagt dem Kaeufer Bescheid, dass er wieder kaufen kann (LP-POSTPAID-009).
 *
 * Abweichung vom Ticketnamen `UnblockWalletWhenSettled`: Aufgehoben wird die
 * Sperre nicht hier, sondern im WalletService, in derselben Transaktion wie
 * die Buchung, die den Saldo ausgeglichen hat -- ein Kaeufer, der gezahlt hat,
 * soll im selben Moment wieder kaufen koennen und nicht erst, wenn ein Job
 * gelaufen ist. Dieser Zuhoerer haengt an der Aufhebung und verschickt nur
 * noch die Meldung; ein Name, der etwas anderes verspricht, waere die
 * schlechtere Wahl.
 *
 * **In der Queue.** Der Ausgleich passiert im Webhook einer Zahlung oder in
 * der Abrechnung eines Leads; an einem Mailserver darf beides nicht haengen.
 */
class NotifyWalletUnblocked implements ShouldQueue
{
    /** @var int Ein kurz nicht erreichbarer Mailserver soll sich von selbst erledigen. */
    public int $tries = 3;

    public function handle(WalletUnblocked $event): void
    {
        $wallet = Wallet::query()->find($event->walletId);

        if (! $wallet instanceof Wallet) {
            return;
        }

        $buyer = $wallet->owner;

        if (! $buyer instanceof Tenant) {
            return;
        }

        // `value()` statt `first()`: Die Beziehung traegt ein eigenes
        // Pivot-Model; gebraucht wird ohnehin nur die Adresse.
        $email = (string) ($buyer->users()->value('users.email') ?? '');

        if ($email === '') {
            Log::warning('Aufhebung der Kaufsperre ohne Empfaenger.', [
                'wallet_id' => $wallet->getKey(),
                'tenant_id' => $buyer->getKey(),
            ]);

            return;
        }

        try {
            Mail::to($email)->send(new WalletUnblockedMail($buyer, $event->balanceCents));
        } catch (Throwable $exception) {
            Log::warning('Meldung zur aufgehobenen Kaufsperre konnte nicht verschickt werden.', [
                'wallet_id' => $wallet->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
