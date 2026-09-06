<?php

declare(strict_types=1);

namespace App\Funnel\Conditions\Comparisons;

use App\Funnel\Conditions\ConditionContext;

/**
 * Antwort ist in der hinterlegten Werteliste enthalten.
 */
class InComparison extends BaseComparison
{
    public function matches(ConditionContext $context): bool
    {
        foreach ($context->answerAsList() as $answer) {
            foreach ($context->expectedAsList() as $expected) {
                if ($this->looselyEquals($answer, $expected)) {
                    return true;
                }
            }
        }

        return false;
    }
}
