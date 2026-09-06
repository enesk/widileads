<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

use App\Models\FunnelQuestion;

/**
 * Zahleneingabe (FB-011). Grenzen kommen aus funnel_questions.validation.
 */
class NumberType extends BaseQuestionType
{
    protected function typeRules(FunnelQuestion $question): array
    {
        return ['numeric'];
    }

    public function normalize(mixed $value, FunnelQuestion $question): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = str_replace(',', '.', trim((string) $value));

        if (! is_numeric($value)) {
            return null;
        }

        return str_contains($value, '.') ? (float) $value : (int) $value;
    }
}
