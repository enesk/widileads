<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

/**
 * Mehrzeiliger Freitext (FB-011).
 */
class TextareaType extends BaseQuestionType
{
    protected function typeRules(QuestionDefinition $question): array
    {
        return ['string', 'max:'.config('funnel.question.textarea_max_length')];
    }
}
