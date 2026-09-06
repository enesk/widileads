<?php

declare(strict_types=1);

namespace App\Funnel\Snapshots;

/**
 * Gelesener JSON-Snapshot eines veroeffentlichten Funnels (FB-012).
 *
 * Bewusst ohne Eloquent und ohne Datenbankzugriff: Die oeffentliche Strecke
 * liest nur Snapshots, und die Auswertung soll ohne Datenbank pruefbar bleiben.
 * Das Format ist in docs/funnel-builder/snapshot-format.md beschrieben; FB-014
 * schreibt Snapshots genau so.
 */
class FunnelSnapshot
{
    /**
     * @param  array<string, mixed>  $funnel
     * @param  list<StepSnapshot>  $steps  nach Position sortiert
     * @param  list<ConditionSnapshot>  $conditions  nach Prioritaet sortiert, hoechste zuerst
     * @param  list<ResultSnapshot>  $results  nach Mindestpunktzahl sortiert
     */
    public function __construct(
        public readonly array $funnel,
        public readonly array $steps,
        public readonly array $conditions,
        public readonly array $results = [],
    ) {}

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function fromArray(array $snapshot): self
    {
        $steps = array_map(
            static fn (array $step): StepSnapshot => StepSnapshot::fromArray($step),
            array_values((array) ($snapshot['steps'] ?? [])),
        );

        usort($steps, static fn (StepSnapshot $a, StepSnapshot $b): int => $a->position <=> $b->position);

        $conditions = array_map(
            static fn (array $condition): ConditionSnapshot => ConditionSnapshot::fromArray($condition),
            array_values((array) ($snapshot['conditions'] ?? [])),
        );

        // Hoechste Prioritaet zuerst; bei Gleichstand bleibt die Reihenfolge des
        // Snapshots erhalten, damit dieselben Antworten immer denselben Weg nehmen.
        usort($conditions, static fn (ConditionSnapshot $a, ConditionSnapshot $b): int => $b->priority <=> $a->priority);

        // Snapshots von vor FB-012a kennen den Auswertungsschritt nicht. Fuer sie
        // gilt die alte Regel: Sie greifen dort, wo ihre Ausgangsfrage steht.
        $conditions = array_map(
            static function (ConditionSnapshot $condition) use ($steps): ConditionSnapshot {
                if ($condition->evaluateAtStepPosition !== null) {
                    return $condition;
                }

                foreach ($steps as $step) {
                    if (in_array($condition->sourceFieldKey, $step->fieldKeys(), true)) {
                        return $condition->evaluatedAt($step->position);
                    }
                }

                return $condition;
            },
            $conditions,
        );

        $results = array_map(
            static fn (array $result): ResultSnapshot => ResultSnapshot::fromArray($result),
            array_values((array) ($snapshot['results'] ?? [])),
        );

        usort($results, static fn (ResultSnapshot $a, ResultSnapshot $b): int => $a->minScore <=> $b->minScore);

        return new self(
            funnel: (array) ($snapshot['funnel'] ?? []),
            steps: $steps,
            conditions: $conditions,
            results: $results,
        );
    }

    /**
     * @return list<int> Positionen aller Schritte, aufsteigend
     */
    public function stepPositions(): array
    {
        return array_map(static fn (StepSnapshot $step): int => $step->position, $this->steps);
    }

    public function stepCount(): int
    {
        return count($this->steps);
    }

    public function hasStep(int $position): bool
    {
        return in_array($position, $this->stepPositions(), true);
    }

    /**
     * Naechster Schritt in der gepflegten Reihenfolge, oder null nach dem letzten.
     */
    public function stepAfter(int $position): ?int
    {
        foreach ($this->stepPositions() as $candidate) {
            if ($candidate > $position) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Regeln, die beim Verlassen eines Schritts ausgewertet werden.
     *
     * Massgeblich ist der Auswertungsschritt der Regel, nicht der Schritt ihrer
     * Ausgangsfrage (FB-012a): Eine Regel darf sich auf eine frueher gegebene
     * Antwort beziehen und trotzdem erst spaeter greifen.
     *
     * @return list<ConditionSnapshot>
     */
    public function conditionsForStep(int $position): array
    {
        return array_values(array_filter(
            $this->conditions,
            static fn (ConditionSnapshot $condition): bool => $condition->evaluateAtStepPosition === $position,
        ));
    }

    /**
     * Alle Fragen des Funnels ueber alle Schritte hinweg.
     *
     * @return list<QuestionSnapshot>
     */
    public function questions(): array
    {
        $questions = [];

        foreach ($this->steps as $step) {
            foreach ($step->questions as $question) {
                $questions[] = $question;
            }
        }

        return $questions;
    }
}
