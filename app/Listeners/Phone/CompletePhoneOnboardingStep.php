<?php

declare(strict_types=1);

namespace App\Listeners\Phone;

use App\Constants\OnboardingStep;
use App\Events\Phone\PhoneVerified;
use App\Models\Tenant;
use App\Services\Onboarding\OnboardingService;

/**
 * Hakt `phone_verified` in allen Workspaces des Nutzers ab (LP-ONBOARD-004).
 *
 * Die Wahrheit der Bestaetigung steht am Benutzer; der Haken in
 * `tenant_onboarding_steps` ist die Spiegelung davon, damit Checkliste und
 * Admin-Trichter alle Schritte aus einer Tabelle lesen (LP-ONBOARD-002). Er
 * wird in jedem Workspace gesetzt, in dem der Nutzer Mitglied ist -- der
 * Fortschritt eines Workspaces soll nicht davon abhaengen, welchen er gerade
 * geoeffnet hatte.
 *
 * Nicht in die Queue: Direkt nach der Bestaetigung landet der Nutzer im
 * Dashboard, und ein Haken, der erst Sekunden spaeter erscheint, sieht dort wie
 * ein Fehler aus. Die Arbeit ist ein Insert je Workspace.
 *
 * Idempotent durch den Unique-Index (tenant_id, step) im OnboardingService --
 * eine zurueckgesetzte und erneut durchlaufene Bestaetigung erzeugt keinen
 * zweiten Haken und keine zweite Mail.
 */
class CompletePhoneOnboardingStep
{
    public function __construct(private readonly OnboardingService $onboarding) {}

    public function handle(PhoneVerified $event): void
    {
        $event->user->tenants()->each(function (Tenant $workspace) use ($event): void {
            $this->onboarding->complete(
                $workspace,
                OnboardingStep::PHONE_VERIFIED,
                $event->user,
                ['channel' => $event->channel->value],
            );

            // Die Sperre liest am Benutzer, der Fortschritt im Cache aber am
            // Workspace: Ohne das Verwerfen zeigte die Checkliste bis zu 30
            // Sekunden weiter den alten Stand.
            $this->onboarding->forgetProgress($workspace);
        });
    }
}
