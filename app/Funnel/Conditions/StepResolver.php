<?php

declare(strict_types=1);

namespace App\Funnel\Conditions;

use App\Exceptions\FunnelStepCycleException;
use App\Funnel\Snapshots\ConditionSnapshot;
use App\Funnel\Snapshots\FunnelSnapshot;

/**
 * Bestimmt den naechsten Schritt einer Funnel-Strecke (FB-012).
 *
 * Regel: Unter den Verzweigungsregeln des aktuellen Schritts gewinnt die mit der
 * hoechsten Prioritaet, deren Bedingung zutrifft. Greift keine, geht es mit dem
 * naechsten Schritt in der gepflegten Reihenfolge weiter; nach dem letzten
 * Schritt liefert der Resolver null.
 *
 * Gearbeitet wird ausschliesslich auf dem Snapshot (FB-014 liefert ihn spaeter
 * aus funnel_versions) -- keine Datenbankabfrage, damit dieselbe Auswertung in
 * Runtime, API und Test identisch laeuft.
 */
class StepResolver
{
    public function __construct(private readonly ConditionComparisonRegistry $comparisons) {}

    /**
     * Naechster Schritt nach `$currentStep`, oder null am Ende der Strecke.
     *
     * @param  array<string, mixed>  $answers  Antworten als field_key => Wert
     * @param  int  $score  bis hierhin erreichte Punktzahl (nur fuer score_gte; Berechnung: FB-013)
     */
    public function next(FunnelSnapshot $snapshot, array $answers, int $currentStep, int $score = 0): ?int
    {
        foreach ($snapshot->conditionsForStep($currentStep) as $condition) {
            if (! $this->matches($condition, $answers, $score)) {
                continue;
            }

            // Ein Ziel, das es nicht gibt, wird ignoriert statt den Endkunden
            // ins Leere zu schicken -- die naechste Regel bekommt ihre Chance.
            if ($snapshot->hasStep($condition->targetStepPosition)) {
                return $condition->targetStepPosition;
            }
        }

        return $snapshot->stepAfter($currentStep);
    }

    /**
     * Vollstaendiger Weg durch den Funnel fuer einen Satz Antworten -- vom ersten
     * Schritt bis zum Ende. Hier greift der Zyklenschutz: Fuehren die Regeln im
     * Kreis, bricht der Weg mit einer Fachausnahme ab, statt endlos zu laufen.
     *
     * @param  array<string, mixed>  $answers
     * @return list<int>
     *
     * @throws FunnelStepCycleException
     */
    public function path(FunnelSnapshot $snapshot, array $answers, int $score = 0): array
    {
        $positions = $snapshot->stepPositions();

        if ($positions === []) {
            return [];
        }

        $maxVisits = $snapshot->stepCount() * (int) config('funnel.runtime.max_step_visit_factor');
        $current = $positions[0];
        $path = [$current];

        while (($current = $this->next($snapshot, $answers, $current, $score)) !== null) {
            $path[] = $current;

            if (count($path) > $maxVisits) {
                throw FunnelStepCycleException::forStep($current, $maxVisits);
            }
        }

        return $path;
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function matches(ConditionSnapshot $condition, array $answers, int $score): bool
    {
        $context = new ConditionContext(
            answer: $answers[$condition->sourceFieldKey] ?? null,
            expected: $condition->value,
            score: $score,
        );

        return $this->comparisons->for($condition->operator)->matches($context);
    }
}
