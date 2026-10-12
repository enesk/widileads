<?php

declare(strict_types=1);

namespace App\Events\Onboarding;

use App\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Alle Pflichtschritte des Einrichtungs-Wizards sind erledigt
 * (LP-ONBOARD-002).
 *
 * Ab hier kommt der Kaeufer am Wizard vorbei. Das Ereignis feuert nur beim
 * echten Abschluss, nicht beim Ueberspringen -- wer den Wizard ueberspringt,
 * hat ihn nicht abgeschlossen, und die Mailstrecke behandelt beide Faelle
 * unterschiedlich.
 */
class OnboardingWizardCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Tenant $workspace) {}
}
