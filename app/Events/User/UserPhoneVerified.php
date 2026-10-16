<?php

namespace App\Events\User;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ein Nutzer hat seine Rufnummer bestaetigt (Ereignis aus SaaSykit).
 *
 * Bleibt bewusst als Schnittstelle bestehen: An ihm haengt
 * App\Listeners\User\ActivateSubscriptionsPendingUserVerification, das Abos
 * freischaltet, die auf die Verifizierung warten.
 *
 * Ausgeloest wird es seit Ticket #97 nur noch von
 * App\Services\CallerIdService (im Rueckruf des Bestaetigungsanrufs),
 * zusammen mit dem reicheren App\Events\Phone\PhoneVerified. Die beiden
 * frueheren Wege -- App\Services\UserVerificationService und der SMS-Weg
 * ueber Twilio Verify -- sind abgebaut.
 */
class UserPhoneVerified
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public User $user,
    ) {
        //
    }
}
