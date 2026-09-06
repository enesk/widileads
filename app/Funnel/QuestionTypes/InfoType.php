<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

use App\Models\FunnelQuestion;

/**
 * Reiner Textschritt ohne Eingabe (FB-011). Er erwartet keine Antwort, deshalb
 * gibt es auch nichts zu validieren oder zu speichern.
 */
class InfoType extends BaseQuestionType
{
    protected function typeRules(FunnelQuestion $question): array
    {
        return [];
    }

    public function normalize(mixed $value, FunnelQuestion $question): mixed
    {
        return null;
    }

    public function expectsAnswer(): bool
    {
        return false;
    }
}
