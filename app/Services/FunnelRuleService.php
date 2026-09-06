<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\ConditionOperator;
use App\Funnel\Results\ResultRangeReport;
use App\Funnel\Results\ResultRangeValidator;
use App\Funnel\Scoring\ScoreCalculator;
use App\Funnel\Snapshots\FunnelSnapshot;
use App\Funnel\Snapshots\SnapshotBuilder;
use App\Models\Funnel;
use App\Models\FunnelCondition;
use App\Models\FunnelResult;

/**
 * Pflegt Verzweigungsregeln und Ergebnis-Screens eines Funnels (FB-016).
 *
 * Geprueft wird mit demselben Werkzeug, das auch beim Veroeffentlichen laeuft:
 * ResultRangeValidator aus FB-013 gegen den Snapshot des Entwurfsstands. Ein
 * eigener Validator im Builder wuerde frueher oder spaeter andere Ergebnisse
 * liefern als PublishFunnel - und dann waere unklar, welcher recht hat.
 */
class FunnelRuleService
{
    public function __construct(
        private readonly SnapshotBuilder $snapshotBuilder,
        private readonly ResultRangeValidator $resultRangeValidator,
        private readonly ScoreCalculator $scoreCalculator,
    ) {}

    public function addCondition(Funnel $funnel): FunnelCondition
    {
        $question = $funnel->questions()->orderBy('position')->first();
        $targetStep = $funnel->steps()->orderByDesc('position')->first();

        return $funnel->conditions()->create([
            'source_question_id' => $question?->id,
            'operator' => ConditionOperator::EQUALS,
            'value' => [],
            'target_step_id' => $targetStep?->id,
            'priority' => $this->nextPriority($funnel),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateCondition(FunnelCondition $condition, array $attributes): FunnelCondition
    {
        $condition->fill(array_intersect_key($attributes, array_flip([
            'source_question_id',
            'operator',
            'value',
            'target_step_id',
            'evaluate_at_step_position',
            'priority',
        ])))->save();

        return $condition->refresh();
    }

    public function deleteCondition(FunnelCondition $condition): void
    {
        $condition->delete();
    }

    public function addResult(Funnel $funnel): FunnelResult
    {
        $highest = (int) $funnel->results()->max('max_score');

        return $funnel->results()->create([
            'min_score' => $highest + 1,
            'max_score' => $highest + 1,
            'title' => __('builder.rules.new_result'),
            'show_contact_form' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateResult(FunnelResult $result, array $attributes): FunnelResult
    {
        $result->fill(array_intersect_key($attributes, array_flip([
            'min_score',
            'max_score',
            'title',
            'body',
            'cta_label',
            'cta_url',
            'show_contact_form',
        ])))->save();

        return $result->refresh();
    }

    public function deleteResult(FunnelResult $result): void
    {
        $result->delete();
    }

    /**
     * Prueft die Ergebnisbereiche des Entwurfsstands - gegen den tatsaechlich
     * erreichbaren Punktebereich, nicht nur die Bereiche untereinander.
     */
    public function checkResultRanges(Funnel $funnel): ResultRangeReport
    {
        return $this->resultRangeValidator->validate($this->draftSnapshot($funnel));
    }

    /**
     * Hoechste erreichbare Punktzahl des Entwurfs. Die Oberflaeche zeichnet die
     * Bereichsvorschau darauf, damit sichtbar wird, wo Luecken liegen.
     */
    public function highestReachableScore(Funnel $funnel): int
    {
        return $this->scoreCalculator->maximumScore($this->draftSnapshot($funnel));
    }

    private function draftSnapshot(Funnel $funnel): FunnelSnapshot
    {
        return FunnelSnapshot::fromArray($this->snapshotBuilder->build($funnel->fresh() ?? $funnel));
    }

    private function nextPriority(Funnel $funnel): int
    {
        return ((int) $funnel->conditions()->max('priority')) + 10;
    }
}
