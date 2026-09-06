<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

/**
 * Deutsche Postleitzahl (FB-011): genau fuenf Ziffern.
 */
class PostalCodeType extends BaseQuestionType
{
    protected function typeRules(QuestionDefinition $question): array
    {
        return ['string', 'regex:'.config('funnel.question.postal_code_pattern')];
    }

    public function normalize(mixed $value, QuestionDefinition $question): mixed
    {
        $value = parent::normalize($value, $question);

        if (! is_string($value)) {
            return $value;
        }

        // Leerzeichen und Trenner entfernen ("12 345" und "12-345" sind dieselbe PLZ).
        return preg_replace('/[^0-9]/', '', $value);
    }
}
