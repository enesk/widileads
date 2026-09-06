<?php

declare(strict_types=1);

namespace App\Funnel\Conditions\Comparisons;

use App\Funnel\Conditions\ConditionContext;

/**
 * Antwort ist groesser als der Wert. Nicht-numerische Antworten treffen nie.
 */
class GtComparison extends BaseComparison
{
    public function matches(ConditionContext $context): bool
    {
        $answer = $this->toNumber($context->answer);
        $expected = $this->toNumber($context->expectedScalar());

        return $answer !== null && $expected !== null && $answer > $expected;
    }
}
