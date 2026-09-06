<?php

declare(strict_types=1);

namespace App\Funnel\Support;

/**
 * Wertevergleich ohne Typfallen -- die eine Stelle, an der entschieden wird, ob
 * zwei Antwortwerte dasselbe meinen.
 *
 * Herausgeloest aus BaseComparison (FB-012), weil der Lead-Marktplatz (FB-051)
 * dieselbe Frage stellt: Eine Verzweigungsregel "tierart ist hund" und ein
 * Kaufkriterium "tierart in [hund]" muessen denselben Lead treffen. Zwei
 * Implementierungen wuerden frueher oder spaeter auseinanderlaufen, und dann
 * saehe ein Kaeufer Leads im Marktplatz, die der Funnel anders bewertet hat.
 *
 * Rein: keine Datenbank, kein Zustand, keine Abhaengigkeiten.
 */
final class LooseValueComparison
{
    /**
     * "7" und 7 sind dieselbe Antwort, "Hund" und "hund" auch.
     *
     * Ein Funnel-Ersteller pflegt Werte von Hand, ein Browser liefert alles als
     * String -- ein strikter Vergleich wuerde reihenweise richtige Antworten
     * verwerfen.
     */
    public static function equals(mixed $answer, mixed $expected): bool
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

        $answerNumber = self::toNumber($answer);
        $expectedNumber = self::toNumber($expected);

        if ($answerNumber !== null && $expectedNumber !== null) {
            return abs($answerNumber - $expectedNumber) < PHP_FLOAT_EPSILON;
        }

        return mb_strtolower(trim((string) $answer)) === mb_strtolower(trim((string) $expected));
    }

    /**
     * Zahl aus einer Antwort, oder null wenn sie keine ist. Das Komma als
     * Dezimaltrenner wird mitgelesen ("7,5").
     */
    public static function toNumber(mixed $value): ?float
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
