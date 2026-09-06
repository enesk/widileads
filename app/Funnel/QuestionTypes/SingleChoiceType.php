<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

use App\Models\FunnelQuestion;

/**
 * Genau eine Antwortoption (FB-011).
 */
class SingleChoiceType extends BaseQuestionType
{
    protected function typeRules(FunnelQuestion $question): array
    {
        $values = $this->optionValues($question);

        return $values === [] ? ['string'] : ['string', 'in:'.implode(',', $values)];
    }
}
