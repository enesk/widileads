<?php

declare(strict_types=1);

namespace App\Funnel\Conditions;

/**
 * Was ein Operator zum Vergleichen braucht (FB-012).
 *
 * `answer` ist die Antwort des Endkunden auf die Quellfrage, `expected` der in
 * der Regel hinterlegte Vergleichswert, `score` die bis zum aktuellen Schritt
 * erreichte Punktzahl (nur `score_gte` liest sie; berechnet wird sie in FB-013).
 */
class ConditionContext
{
    public function __construct(
        public readonly mixed $answer,
        public readonly mixed $expected,
        public readonly int $score = 0,
    ) {}

    /**
     * Wurde die Quellfrage ueberhaupt beantwortet? Leerer String, leeres Array
     * und null gelten als unbeantwortet.
     */
    public function isAnswered(): bool
    {
        if ($this->answer === null || $this->answer === '' || $this->answer === []) {
            return false;
        }

        return ! (is_string($this->answer) && trim($this->answer) === '');
    }

    /**
     * Antwort als Liste -- Mehrfachauswahlen sind Arrays, alles andere wird zu
     * einem einelementigen Array, damit Vergleiche nicht zwei Faelle brauchen.
     *
     * @return list<mixed>
     */
    public function answerAsList(): array
    {
        return is_array($this->answer) ? array_values($this->answer) : [$this->answer];
    }

    /**
     * Vergleichswert als Liste (fuer `in`).
     *
     * @return list<mixed>
     */
    public function expectedAsList(): array
    {
        return is_array($this->expected) ? array_values($this->expected) : [$this->expected];
    }

    /**
     * Erster Vergleichswert -- eine Regel darf ihren Wert auch als einelementige
     * Liste hinterlegen ("value": ["hund"]).
     */
    public function expectedScalar(): mixed
    {
        if (! is_array($this->expected)) {
            return $this->expected;
        }

        return array_values($this->expected)[0] ?? null;
    }
}
