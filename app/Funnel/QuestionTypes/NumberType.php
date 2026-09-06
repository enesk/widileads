<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

/**
 * Zahleneingabe (FB-011). Grenzen kommen aus funnel_questions.validation.
 */
class NumberType extends BaseQuestionType
{
    protected function typeRules(QuestionDefinition $question): array
    {
        return ['numeric'];
    }

    public function normalize(mixed $value, QuestionDefinition $question): mixed
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
