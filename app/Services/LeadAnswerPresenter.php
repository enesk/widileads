<?php

declare(strict_types=1);

namespace App\Services;

use App\Funnel\Snapshots\FunnelSnapshot;
use App\Funnel\Snapshots\QuestionSnapshot;
use App\Models\Lead;
use App\Models\LeadAnswer;

/**
 * Die Qualifizierungsantworten eines Leads fuer Empfaenger ausserhalb der
 * Anwendung: Lead-API (FB-030d) und Webhooks (FB-030e).
 *
 * Beide liefern dasselbe Format, deshalb liegt die Aufbereitung an einer Stelle.
 * Die reservierten Kontaktfelder bleiben draussen: Sie erscheinen
 * ausschliesslich unter `contact`, damit die Maskierung nicht ueber die
 * Rohantworten zu umgehen ist.
 */
class LeadAnswerPresenter
{
    /**
     * @return list<array{field_key: string, label: string, value: mixed, value_label: string|null}>
     */
    public function answers(Lead $lead): array
    {
        $questions = $this->questionsByFieldKey($lead);

        return $lead->answers
            ->reject(static fn (LeadAnswer $answer): bool => $answer->isPersonal())
            ->map(function (LeadAnswer $answer) use ($questions): array {
                $question = $questions[$answer->field_key] ?? null;

                return [
                    'field_key' => $answer->field_key,
                    'label' => $question instanceof QuestionSnapshot ? $question->label : $answer->field_key,
                    'value' => $answer->value,
                    'value_label' => $this->valueLabel($question, $answer->value),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Die Fragen der Fassung, aus der dieser Lead stammt -- nach Feldschluessel.
     *
     * Gelesen wird der Snapshot, nicht die Live-Tabellen: Der Lead soll mit den
     * Beschriftungen erscheinen, die der Endkunde gesehen hat.
     *
     * @return array<string, QuestionSnapshot>
     */
    private function questionsByFieldKey(Lead $lead): array
    {
        $snapshot = $lead->funnelVersion?->snapshot;

        if (! is_array($snapshot)) {
            return [];
        }

        $questions = [];

        foreach (FunnelSnapshot::fromArray($snapshot)->questions() as $question) {
            $questions[$question->fieldKey] = $question;
        }

        return $questions;
    }

    private function valueLabel(?QuestionSnapshot $question, mixed $value): ?string
    {
        if ($question === null || $question->options === [] || $value === null) {
            return null;
        }

        $values = is_array($value) ? array_values($value) : [$value];
        $labels = [];

        foreach ($values as $given) {
            foreach ($question->options as $option) {
                if ($option->matches($given)) {
                    $labels[] = $option->label;

                    break;
                }
            }
        }

        return $labels === [] ? null : implode(', ', $labels);
    }
}
