<?php

declare(strict_types=1);

namespace App\Funnel\Conditions\Comparisons;

use App\Funnel\Conditions\ConditionContext;

/**
 * Antwort entspricht dem Wert. Bei einer Mehrfachauswahl genuegt es, dass der
 * Wert unter den angekreuzten Optionen ist.
 */
class EqualsComparison extends BaseComparison
{
    public function matches(ConditionContext $context): bool
    {
        $expected = $context->expectedScalar();

        foreach ($context->answerAsList() as $answer) {
            if ($this->looselyEquals($answer, $expected)) {
                return true;
            }
        }

        return false;
    }
}
