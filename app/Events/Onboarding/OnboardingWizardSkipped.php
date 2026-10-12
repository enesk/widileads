<?php

declare(strict_types=1);

namespace App\Events\Onboarding;

use App\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Der Kaeufer hat den Einrichtungs-Wizard uebersprungen (LP-ONBOARD-014).
 *
 * Das Gegenstueck zu OnboardingWizardCompleted und aus demselben Grund ein
 * eigenes Ereignis: Beide Wege fuehren aus dem Wizard heraus und beide
 * starten die Mailstrecke, aber sie bedeuten nicht dasselbe -- wer
 * uebersprungen hat, hat weder Branchen noch Region, und die Mails reden
 * entsprechend anders mit ihm.
 *
 * Feuert genau einmal: skipWizard() setzt den Zeitstempel unter der
 * Bedingung, dass er leer ist, und loest nur aus, wenn die Zeile sich
 * wirklich geaendert hat.
 */
class OnboardingWizardSkipped
{
    use Dispatchable, SerializesModels;

    public function __construct(public Tenant $workspace) {}
}
