<?php

declare(strict_types=1);

namespace App\Funnel\Snapshots;

use App\Models\Funnel;
use App\Models\FunnelCondition;
use App\Models\FunnelOption;
use App\Models\FunnelQuestion;
use App\Models\FunnelResult;
use App\Models\FunnelStep;

/**
 * Schreibt einen Funnel in das Snapshot-Format (FB-014).
 *
 * Das Format ist in docs/funnel-builder/snapshot-format.md festgeschrieben und
 * wird von StepResolver, ScoreCalculator und ResultResolver gelesen. Adressiert
 * wird ueber Schrittposition und Feldschluessel, nie ueber Datenbank-IDs: Der
 * Snapshot muss auch dann noch stimmen, wenn die Live-Tabellen sich aendern oder
 * der Funnel dupliziert wird.
 */
class SnapshotBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Funnel $funnel): array
    {
        $funnel->loadMissing([
            'steps.questions.options',
            'conditions.sourceQuestion',
            'conditions.targetStep',
            'results',
        ]);

        return [
            'funnel' => [
                'public_token' => $funnel->public_token,
                'name' => $funnel->name,
                'slug' => $funnel->slug,
                'lead_price' => $funnel->effectiveLeadPrice(),
                'contact_step_position' => $funnel->contact_step_position,
                'settings' => $funnel->settings ?? [],
            ],
            'steps' => $funnel->steps->map(fn (FunnelStep $step): array => $this->step($step))->values()->all(),
            'conditions' => $funnel->conditions
                ->map(fn (FunnelCondition $condition): ?array => $this->condition($condition))
                ->filter()
                ->values()
                ->all(),
            'results' => $funnel->results
                ->sortBy('min_score')
                ->map(fn (FunnelResult $result): array => $this->result($result))
                ->values()
                ->all(),
            // Das Theme kommt mit FB-017; der Schluessel steht bereits hier,
            // damit die Runtime ihn ab dann ohne Formataenderung findet.
            'theme' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function step(FunnelStep $step): array
    {
        return [
            'position' => $step->position,
            'title' => $step->title,
            'description' => $step->description,
            'questions' => $step->questions
                ->sortBy('position')
                ->map(fn (FunnelQuestion $question): array => $this->question($question))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function question(FunnelQuestion $question): array
    {
        return [
            'field_key' => $question->field_key,
            'type' => $question->type->value,
            'label' => $question->label,
            'help_text' => $question->help_text,
            'required' => $question->required,
            'position' => $question->position,
            'validation' => $question->validation ?? [],
            'meta' => $question->meta ?? [],
            'options' => $question->options
                ->sortBy('position')
                ->map(fn (FunnelOption $option): array => [
                    'value' => $option->value,
                    'label' => $option->label,
                    'score' => $option->score,
                    'position' => $option->position,
                    'image_path' => $option->image_path,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Eine Regel ohne aufloesbare Quellfrage oder Zielschritt gehoert nicht in
     * den Snapshot -- sie wuerde die Strecke nur ins Leere schicken.
     *
     * @return array<string, mixed>|null
     */
    private function condition(FunnelCondition $condition): ?array
    {
        $sourceFieldKey = $condition->sourceQuestion?->field_key;
        $targetStepPosition = $condition->targetStep?->position;

        if ($sourceFieldKey === null || $targetStepPosition === null) {
            return null;
        }

        return [
            'source_field_key' => $sourceFieldKey,
            'operator' => $condition->operator->value,
            'value' => $condition->value,
            'target_step_position' => $targetStepPosition,
            // Wann die Regel greift -- ohne eigenen Wert der Schritt der
            // Ausgangsfrage (FB-012a).
            'evaluate_at_step_position' => $condition->evaluationStepPosition(),
            'priority' => $condition->priority,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function result(FunnelResult $result): array
    {
        return [
            // Stabiler Schluessel innerhalb der Version: Der Lead haelt spaeter
            // funnel_version_id plus diesen Schluessel statt einer
            // funnel_results-ID (FB-031), damit er auch nach Aenderungen an der
            // Live-Tabelle noch auf dasselbe Ergebnis zeigt.
            'key' => ResultSnapshot::keyFor($result->min_score, $result->max_score),
            'min_score' => $result->min_score,
            'max_score' => $result->max_score,
            'title' => $result->title,
            'body' => $result->body,
            'cta_label' => $result->cta_label,
            'cta_url' => $result->cta_url,
            'show_contact_form' => $result->show_contact_form,
        ];
    }
}
