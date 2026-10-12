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
 * Ausgeloest wird es seit LP-ONBOARD-023 nur noch von
 * App\Services\Phone\PhoneVerificationService, zusammen mit dem reicheren
 * App\Events\Phone\PhoneVerified. Der zweite Weg ueber
 * App\Services\UserVerificationService ist abgebaut.
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
