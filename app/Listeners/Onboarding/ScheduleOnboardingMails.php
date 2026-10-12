<?php

declare(strict_types=1);

namespace App\Listeners\Onboarding;

use App\Constants\OnboardingMailKey;
use App\Events\Onboarding\OnboardingWizardCompleted;
use App\Events\Onboarding\OnboardingWizardSkipped;
use App\Jobs\Onboarding\SendOnboardingMail;

/**
 * Plant die vier Mails der Onboarding-Strecke (LP-ONBOARD-014).
 *
 * Ausloeser ist das Verlassen des Wizards -- abgeschlossen oder
 * uebersprungen. Beides zaehlt: Wer die Einrichtung wegklickt, ist genau der
 * Kaeufer, den die Strecke erreichen soll. Beide Ereignisse feuern nur
 * einmal je Workspace (der Zeitstempel wird unter Bedingung gesetzt), und
 * selbst wenn sie es nicht taeten, haelt der Unique-Index in
 * `onboarding_mails_sent` die Mails einzeln.
 *
 * Geplant wird mit Verzoegerung statt mit einem taeglichen Kommando: Die
 * Abstaende stehen am Enum, jeder Job traegt seinen eigenen, und es gibt
 * keine Liste, die jemand pflegen muss. Die Nachtruhe wird schon hier
 * eingerechnet -- der Job prueft sie beim Laufen noch einmal.
 *
 * Nicht in der Queue: Der Zuhoerer reiht nur vier Jobs ein, das ist billiger
 * als der Umweg ueber einen eigenen Queue-Eintrag. Alles, was Zeit kostet,
 * passiert in den Jobs.
 */
class ScheduleOnboardingMails
{
    public function handle(OnboardingWizardCompleted|OnboardingWizardSkipped $event): void
    {
        $workspaceId = (int) $event->workspace->getKey();

        foreach (OnboardingMailKey::cases() as $key) {
            $due = now()->addDays($key->delayDays());

            SendOnboardingMail::dispatch($workspaceId, $key)
                ->delay(SendOnboardingMail::sendableAt($due) ?? $due);
        }
    }
}
