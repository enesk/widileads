<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

/**
 * Genau eine Antwortoption (FB-011).
 */
class SingleChoiceType extends BaseQuestionType
{
    protected function typeRules(QuestionDefinition $question): array
    {
        $values = $this->optionValues($question);

        return $values === [] ? ['string'] : ['string', 'in:'.implode(',', $values)];
    }
}
