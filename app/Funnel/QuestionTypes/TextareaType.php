<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

use App\Models\FunnelQuestion;

/**
 * Mehrzeiliger Freitext (FB-011).
 */
class TextareaType extends BaseQuestionType
{
    protected function typeRules(FunnelQuestion $question): array
    {
        return ['string', 'max:'.config('funnel.question.textarea_max_length')];
    }
}
