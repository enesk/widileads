<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

use App\Models\FunnelQuestion;

/**
 * Mehrere Antwortoptionen (FB-011). Die Antwort ist immer eine Liste, auch bei
 * nur einem angekreuzten Wert -- das erspart der Auswertung eine Fallunterscheidung.
 */
class MultiChoiceType extends BaseQuestionType
{
    protected function typeRules(FunnelQuestion $question): array
    {
        return ['array'];
    }

    public function normalize(mixed $value, FunnelQuestion $question): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $values = is_array($value) ? $value : [$value];
        $values = array_values(array_unique(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $values),
            static fn (string $item): bool => $item !== '',
        )));

        return $values === [] ? null : $values;
    }
}
