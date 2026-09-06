<?php

declare(strict_types=1);

namespace App\Funnel\Conditions;

use App\Constants\ConditionOperator;

/**
 * Auswertung eines einzelnen Vergleichsoperators (FB-012).
 *
 * Jeder Operator aus ConditionOperator hat genau eine Klasse in
 * app/Funnel/Conditions/Comparisons. Aufgeloest wird sie ueber die
 * ConditionComparisonRegistry nach Namenskonvention, damit ein neuer Operator
 * keine bestehende Verzweigung anfasst.
 */
interface ConditionComparison
{
    public function operator(): ConditionOperator;

    public function matches(ConditionContext $context): bool;
}
