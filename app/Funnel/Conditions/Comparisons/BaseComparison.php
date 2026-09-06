<?php

declare(strict_types=1);

namespace App\Funnel\Conditions\Comparisons;

use App\Constants\ConditionOperator;
use App\Funnel\Conditions\ConditionComparison;
use Illuminate\Support\Str;

/**
 * Gemeinsames Verhalten aller Vergleichsoperatoren (FB-012).
 *
 * Der Operator ergibt sich aus dem Klassennamen (ScoreGteComparison ->
 * score_gte), damit eine neue Vergleichsart nichts ausser ihrer eigenen Klasse
 * braucht.
 */
abstract class BaseComparison implements ConditionComparison
{
    public function operator(): ConditionOperator
    {
        return ConditionOperator::from(Str::snake(Str::beforeLast(class_basename($this), 'Comparison')));
    }

    /**
     * Vergleich ohne Typfallen: "7" und 7 sind dieselbe Antwort, "Hund" und
     * "hund" auch. Ein Funnel-Ersteller pflegt Werte von Hand, ein Browser
     * liefert alles als String -- ein strikter Vergleich wuerde hier reihenweise
     * richtige Antworten verwerfen.
     */
    protected function looselyEquals(mixed $answer, mixed $expected): bool
    {
        if ($answer === null || $expected === null) {
            return $answer === $expected;
        }

        if (is_bool($answer) || is_bool($expected)) {
            return filter_var($answer, FILTER_VALIDATE_BOOLEAN) === filter_var($expected, FILTER_VALIDATE_BOOLEAN);
        }

        if (! is_scalar($answer) || ! is_scalar($expected)) {
            return $answer == $expected;
        }

        $answerNumber = $this->toNumber($answer);
        $expectedNumber = $this->toNumber($expected);

        if ($answerNumber !== null && $expectedNumber !== null) {
            return abs($answerNumber - $expectedNumber) < PHP_FLOAT_EPSILON;
        }

        return mb_strtolower(trim((string) $answer)) === mb_strtolower(trim((string) $expected));
    }

    /**
     * Zahl aus einer Antwort, oder null wenn sie keine ist. Das Komma als
     * Dezimaltrenner wird mitgelesen ("7,5").
     */
    protected function toNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = str_replace(',', '.', trim($value));

        return is_numeric($value) ? (float) $value : null;
    }
}
