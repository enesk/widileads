<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

/**
 * Einzeiliger Freitext (FB-011).
 */
class TextType extends BaseQuestionType
{
    protected function typeRules(QuestionDefinition $question): array
    {
        return ['string', 'max:'.config('funnel.question.text_max_length')];
    }
}
