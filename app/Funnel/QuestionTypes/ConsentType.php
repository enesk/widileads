<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

/**
 * Einwilligung (FB-011). Eine Pflicht-Einwilligung muss angenommen werden --
 * "nein" ist dann keine gueltige Antwort, sondern ein Validierungsfehler.
 */
class ConsentType extends BaseQuestionType
{
    protected function typeRules(QuestionDefinition $question): array
    {
        return $question->isAnswerRequired() ? ['accepted'] : ['boolean'];
    }

    public function normalize(mixed $value, QuestionDefinition $question): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
