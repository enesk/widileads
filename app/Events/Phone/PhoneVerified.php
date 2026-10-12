<?php

declare(strict_types=1);

namespace App\Events\Phone;

use App\Constants\PhoneVerificationChannel;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ein Nutzer hat seine Rufnummer bestaetigt (LP-ONBOARD-004).
 *
 * Feuert genau einmal je Bestaetigung, nach dem Festschreiben:
 * `users.phone_number_verified_at` und `phone_e164` stehen also schon, und die
 * Sperre EnsurePhoneVerified laesst den Nutzer durch. Zuhoerer duerfen den
 * Nutzer deshalb als freigeschaltet behandeln.
 *
 * Wird eine Bestaetigung im Admin zurueckgesetzt und danach neu durchlaufen,
 * feuert das Ereignis erneut -- die Zuhoerer sind idempotent (Onboarding-Haken
 * und Rufnummernanzeige entstehen nur, wenn sie fehlen).
 *
 * Das aeltere App\Events\User\UserPhoneVerified aus SaaSykit wird daneben
 * weiter ausgeloest: An ihm haengt die Freischaltung von Abos, die auf die
 * Verifizierung warten. Ein Umbau dieses Zuhoerers gehoert nicht in dieses
 * Ticket.
 */
class PhoneVerified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public PhoneVerificationChannel $channel,
    ) {}
}
