<?php

declare(strict_types=1);

namespace App\Funnel\Conditions\Comparisons;

use App\Funnel\Conditions\ConditionContext;

/**
 * Die bis hierhin erreichte Punktzahl ist mindestens so hoch wie der Wert.
 * Anders als alle anderen Operatoren liest dieser nicht die Antwort, sondern den
 * Punktestand aus dem Kontext (berechnet wird er in FB-013).
 */
class ScoreGteComparison extends BaseComparison
{
    public function matches(ConditionContext $context): bool
    {
        $expected = $this->toNumber($context->expectedScalar());

        return $expected !== null && $context->score >= $expected;
    }
}
