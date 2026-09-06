<?php

declare(strict_types=1);

namespace App\Funnel\Conditions\Comparisons;

use App\Funnel\Conditions\ConditionContext;

/**
 * Die Quellfrage wurde beantwortet -- ohne Vergleich des Werts.
 */
class AnsweredComparison extends BaseComparison
{
    public function matches(ConditionContext $context): bool
    {
        return $context->isAnswered();
    }
}
