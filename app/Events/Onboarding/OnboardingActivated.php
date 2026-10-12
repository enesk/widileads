<?php

declare(strict_types=1);

namespace App\Events\Onboarding;

use App\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Der Kaeufer ist aktiviert: er hat einen gekauften Lead angerufen
 * (LP-ONBOARD-002).
 *
 * Das ist die Kennzahl des ganzen Vorhabens -- bezahlt hat er vorher, hier
 * faengt die Arbeit an. Daran haengen die Abbruchregel der Mailstrecke
 * (LP-ONBOARD-014) und die Aktivierungsquote im Admin (LP-ONBOARD-015).
 *
 * Feuert genau einmal, weil der Haken `first_call` nur einmal entstehen kann.
 */
class OnboardingActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Tenant $workspace) {}
}
