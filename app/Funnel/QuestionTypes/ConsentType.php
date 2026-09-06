<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

use App\Models\FunnelQuestion;

/**
 * Einwilligung (FB-011). Eine Pflicht-Einwilligung muss angenommen werden --
 * "nein" ist dann keine gueltige Antwort, sondern ein Validierungsfehler.
 */
class ConsentType extends BaseQuestionType
{
    protected function typeRules(FunnelQuestion $question): array
    {
        return $question->required ? ['accepted'] : ['boolean'];
    }

    public function normalize(mixed $value, FunnelQuestion $question): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
