<?php

declare(strict_types=1);

namespace App\Funnel\Conditions\Comparisons;

use App\Funnel\Conditions\ConditionContext;

/**
 * Antwort entspricht dem Wert nicht. Eine unbeantwortete Frage gilt als
 * ungleich -- wer nichts angibt, hat den erwarteten Wert nicht gewaehlt.
 */
class NotEqualsComparison extends BaseComparison
{
    public function __construct(private readonly EqualsComparison $equals) {}

    public function matches(ConditionContext $context): bool
    {
        return ! $this->equals->matches($context);
    }
}
