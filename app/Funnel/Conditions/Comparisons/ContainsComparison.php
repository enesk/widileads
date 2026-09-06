<?php

declare(strict_types=1);

namespace App\Funnel\Conditions\Comparisons;

use App\Funnel\Conditions\ConditionContext;
use Illuminate\Support\Str;

/**
 * Antwort enthaelt den Wert -- als Teiltext (Gross-/Kleinschreibung egal) oder
 * als Element einer Mehrfachauswahl.
 */
class ContainsComparison extends BaseComparison
{
    public function matches(ConditionContext $context): bool
    {
        $expected = $context->expectedScalar();

        if ($expected === null) {
            return false;
        }

        foreach ($context->answerAsList() as $answer) {
            if ($this->looselyEquals($answer, $expected)) {
                return true;
            }

            if (is_scalar($answer) && Str::contains((string) $answer, (string) $expected, ignoreCase: true)) {
                return true;
            }
        }

        return false;
    }
}
