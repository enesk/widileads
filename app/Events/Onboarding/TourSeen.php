<?php

declare(strict_types=1);

namespace App\Events\Onboarding;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Eine gefuehrte Tour ist durch (LP-ONBOARD-013).
 *
 * Ausgeloest bei Abschluss und bei "Ueberspringen" -- beides heisst fuer die
 * Oberflaeche dasselbe: Der Kaeufer hat sie gesehen und soll sie nicht wieder
 * vorgesetzt bekommen.
 *
 * Das Ereignis ist der ganze Schreibweg der Tour. Der Controller hakt selbst
 * nichts ab: Welcher Onboarding-Schritt zu welcher Tour gehoert, entscheidet
 * der Zuhoerer (LP-ONBOARD-011). Anders als bei den Wizard-Schritten feuert es
 * auch beim zweiten Mal -- wer die Tour aus dem Kontomenue erneut startet,
 * beendet sie erneut, und der Haken steht dann schon. Zuhoerer muessen das
 * aushalten; das Abhaken selbst ist idempotent.
 */
class TourSeen
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $workspace,
        public string $tour,
        public ?User $user = null,
    ) {}
}
