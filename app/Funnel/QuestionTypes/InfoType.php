<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

/**
 * Reiner Textschritt ohne Eingabe (FB-011). Er erwartet keine Antwort, deshalb
 * gibt es auch nichts zu validieren oder zu speichern.
 */
class InfoType extends BaseQuestionType
{
    protected function typeRules(QuestionDefinition $question): array
    {
        return [];
    }

    public function normalize(mixed $value, QuestionDefinition $question): mixed
    {
        return null;
    }

    public function expectsAnswer(): bool
    {
        return false;
    }
}
