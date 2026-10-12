<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Constants\OnboardingStep;

/**
 * Der Onboarding-Stand eines Workspaces (LP-ONBOARD-002).
 *
 * Der OnboardingService gibt bewusst nicht "fertig ja/nein" zurueck, sondern
 * diesen Stand: Wizard, Checkliste im Dashboard, Tour und Mails zeigen alle
 * etwas anderes davon an, muessten es aber sonst jeweils selbst nachrechnen.
 * Dann steht in der Checkliste irgendwann eine andere Zahl als im Trichter.
 *
 * Das Ding ist ein Abbild und kein Zugriff: Es wird im View-Composer einmal je
 * Anfrage gebaut, 30 Sekunden gemerkt und darf deshalb kein Eloquent-Modell
 * mit sich fuehren.
 */
final class OnboardingProgress
{
    /**
     * @param  array<string, bool>  $steps  Schrittwert => erledigt, in der Reihenfolge des Enums
     */
    public function __construct(
        public readonly array $steps,
        public readonly int $percent,
        public readonly ?OnboardingStep $nextAction,
        public readonly bool $wizardCompleted,
        public readonly bool $wizardSkipped,
        public readonly bool $activated,
    ) {}

    public function isCompleted(OnboardingStep $step): bool
    {
        return $this->steps[$step->value] ?? false;
    }

    /**
     * Wie viele Schritte erledigt sind -- die Zahl fuer "3 von 9" in der
     * Checkliste.
     */
    public function completedCount(): int
    {
        return count(array_filter($this->steps));
    }

    public function totalCount(): int
    {
        return count($this->steps);
    }

    /**
     * Ist noch irgendetwas offen? Die Checkliste verschwindet, wenn das
     * nicht mehr gilt.
     */
    public function hasOpenSteps(): bool
    {
        return $this->completedCount() < $this->totalCount();
    }

    /**
     * Die offenen Schritte in der Reihenfolge des Enums.
     *
     * @return list<OnboardingStep>
     */
    public function openSteps(): array
    {
        $open = [];

        foreach ($this->steps as $value => $completed) {
            if (! $completed) {
                $open[] = OnboardingStep::from($value);
            }
        }

        return $open;
    }
}
