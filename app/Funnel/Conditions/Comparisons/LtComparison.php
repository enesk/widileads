<?php

declare(strict_types=1);

namespace App\Funnel\Conditions\Comparisons;

use App\Funnel\Conditions\ConditionContext;

/**
 * Antwort ist kleiner als der Wert. Nicht-numerische Antworten treffen nie.
 */
class LtComparison extends BaseComparison
{
    public function matches(ConditionContext $context): bool
    {
        $answer = $this->toNumber($context->answer);
        $expected = $this->toNumber($context->expectedScalar());

        return $answer !== null && $expected !== null && $answer < $expected;
    }
}
