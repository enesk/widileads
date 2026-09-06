<?php

declare(strict_types=1);

namespace App\Funnel\Conditions\Comparisons;

use App\Constants\ConditionOperator;
use App\Funnel\Conditions\ConditionComparison;
use App\Funnel\Support\LooseValueComparison;
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
     *
     * Die Regel selbst steht in LooseValueComparison, weil der Lead-Marktplatz
     * (FB-051) dieselbe Frage stellt und beide dieselbe Antwort geben muessen.
     */
    protected function looselyEquals(mixed $answer, mixed $expected): bool
    {
        return LooseValueComparison::equals($answer, $expected);
    }

    /**
     * Zahl aus einer Antwort, oder null wenn sie keine ist. Das Komma als
     * Dezimaltrenner wird mitgelesen ("7,5").
     */
    protected function toNumber(mixed $value): ?float
    {
        return LooseValueComparison::toNumber($value);
    }
}
