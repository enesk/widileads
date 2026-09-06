<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

use App\Models\FunnelQuestion;

/**
 * E-Mail-Adresse (FB-011). Wird kleingeschrieben gespeichert, damit Dubletten
 * spaeter ueberhaupt auffallen.
 */
class EmailType extends BaseQuestionType
{
    protected function typeRules(FunnelQuestion $question): array
    {
        return ['string', 'email:rfc,dns', 'max:'.config('funnel.question.text_max_length')];
    }

    public function normalize(mixed $value, FunnelQuestion $question): mixed
    {
        $value = parent::normalize($value, $question);

        return is_string($value) ? mb_strtolower($value) : $value;
    }
}
