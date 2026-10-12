<?php

declare(strict_types=1);

namespace App\Listeners\Onboarding;

use App\Constants\OnboardingStep;
use App\Events\Call\CallAttemptCompleted;
use App\Models\Tenant;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Hakt `first_call` ab, sobald der Kaeufer wirklich gesprochen hat
 * (LP-ONBOARD-012).
 *
 * Das ist der Schritt, der den Kaeufer als aktiviert gilt -- der
 * OnboardingService loest darauf OnboardingActivated aus, und die
 * Aktivierungskarte verschwindet. Deshalb zaehlt hier nicht der Versuch,
 * sondern das Gespraech: unter einer Sekunde Dauer hat niemand mit dem
 * Interessenten geredet, und ein Kaeufer, der nur Nummern klingeln laesst, ist
 * genau der Fall, den dieses Vorhaben verhindern soll.
 *
 * Die Schwelle ist dieselbe, mit der der AttemptClassifier einen Versuch als
 * erreicht bewertet (`duration_seconds` > 0). Bewertet wird sie weiterhin dort
 * -- hier wird nur gelesen.
 *
 * Idempotent ueber den Unique-Index im OnboardingService: Jedes weitere
 * Gespraech laeuft ins Leere, und auch die Aktivierung passiert nur einmal.
 */
class MarkFirstCall implements ShouldQueue
{
    /** Ab dieser Gespraechsdauer gilt der Anruf als gefuehrt. */
    private const MIN_DURATION_SECONDS = 1;

    public function __construct(private readonly OnboardingService $onboarding) {}

    public function handle(CallAttemptCompleted $event): void
    {
        $attempt = $event->attempt;

        if ((int) ($attempt->duration_seconds ?? 0) < self::MIN_DURATION_SECONDS) {
            return;
        }

        // Der Versuch gehoert dem Kaeufer-Workspace (`call_attempts.tenant_id`),
        // nicht dem Betreiber des Leads.
        $workspace = Tenant::query()->whereKey($attempt->tenant_id)->first();

        if (! $workspace instanceof Tenant) {
            return;
        }

        $this->onboarding->complete(
            $workspace,
            OnboardingStep::FIRST_CALL,
            $attempt->user,
            [
                'call_attempt_id' => (int) $attempt->getKey(),
                'duration_seconds' => (int) $attempt->duration_seconds,
            ],
        );
    }
}
