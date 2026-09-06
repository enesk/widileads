<?php

declare(strict_types=1);

namespace App\Funnel\Conditions;

use App\Constants\ConditionOperator;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Loest einen Vergleichsoperator zu seiner Auswertung auf (FB-012).
 *
 * Wie bei den Fragetypen (FB-011) ueber Namenskonvention: score_gte ->
 * ScoreGteComparison im Namensraum App\Funnel\Conditions\Comparisons. Ein neuer
 * Operator braucht damit einen Enum-Case und eine Klasse, sonst nichts.
 */
class ConditionComparisonRegistry
{
    /**
     * @var array<string, ConditionComparison>
     */
    private array $comparisons = [];

    public function __construct(private readonly Container $container) {}

    public function for(ConditionOperator $operator): ConditionComparison
    {
        return $this->comparisons[$operator->value] ??= $this->resolve($operator);
    }

    /**
     * @return array<string, ConditionComparison>
     */
    public function all(): array
    {
        $comparisons = [];

        foreach (ConditionOperator::cases() as $operator) {
            $comparisons[$operator->value] = $this->for($operator);
        }

        return $comparisons;
    }

    private function resolve(ConditionOperator $operator): ConditionComparison
    {
        $class = 'App\\Funnel\\Conditions\\Comparisons\\'.Str::studly($operator->value).'Comparison';

        if (! class_exists($class) || ! is_a($class, ConditionComparison::class, true)) {
            throw new RuntimeException(sprintf(
                'Zum Operator "%s" fehlt die Auswertung %s.',
                $operator->value,
                $class,
            ));
        }

        /** @var ConditionComparison $comparison */
        $comparison = $this->container->make($class);

        return $comparison;
    }
}
