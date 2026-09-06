<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

use App\Models\FunnelQuestion;

/**
 * Einzeiliger Freitext (FB-011).
 */
class TextType extends BaseQuestionType
{
    protected function typeRules(FunnelQuestion $question): array
    {
        return ['string', 'max:'.config('funnel.question.text_max_length')];
    }
}
