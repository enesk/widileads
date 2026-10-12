<?php

declare(strict_types=1);

namespace App\Listeners\Onboarding;

use App\Constants\OnboardingStep;
use App\Events\Onboarding\TourSeen;
use App\Services\Onboarding\OnboardingService;
use App\Support\PortalTour;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Hakt die Touren der Aktivierungskarte ab (LP-ONBOARD-012).
 *
 * Die Zuordnung Tourname -> Onboarding-Schritt kommt aus App\Support\PortalTour
 * und wird hier nicht zum zweiten Mal aufgeschrieben: Rahmen, Route, Controller
 * und dieser Zuhoerer brauchen sie, und sie darf nicht an zwei Stellen
 * auseinanderlaufen. Ein unbekannter Name laeuft still ins Leere -- eine neue
 * Tour ohne eigenen Schritt soll nichts werfen, sondern nichts abhaken.
 *
 * Der einzige Schreibweg der Touren (LP-ONBOARD-013): Der Controller der Tour
 * loest nur TourSeen aus und hakt selbst nichts ab. Mehrfach ausgeloest wird
 * das Ereignis durchaus -- wer die Tour aus dem Kontomenue erneut startet und
 * beendet, meldet erneut. complete() ist ueber den Unique-Index idempotent, der
 * zweite Aufruf aendert nichts und loest kein Ereignis aus.
 */
class MarkTourSeen implements ShouldQueue
{
    public function __construct(private readonly OnboardingService $onboarding) {}

    public function handle(TourSeen $event): void
    {
        $step = PortalTour::step($event->tour);

        if (! $step instanceof OnboardingStep) {
            return;
        }

        $this->onboarding->complete($event->workspace, $step, $event->user);
    }
}
