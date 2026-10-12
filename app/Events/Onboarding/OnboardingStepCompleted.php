<?php

declare(strict_types=1);

namespace App\Events\Onboarding;

use App\Constants\OnboardingStep;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ein Onboarding-Schritt ist erledigt (LP-ONBOARD-002).
 *
 * Feuert genau einmal je Workspace und Schritt -- ein zweites complete() loest
 * nichts aus. Darauf duerfen sich Zuhoerer verlassen: Die Mailstrecke
 * (LP-ONBOARD-014) schickt sonst dieselbe Mail doppelt.
 *
 * Wird nach dem Festschreiben ausgeloest, der Haken steht also schon in der
 * Datenbank.
 */
class OnboardingStepCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $workspace,
        public OnboardingStep $step,
        public ?User $user = null,
    ) {}
}
