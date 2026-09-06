<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

use App\Models\FunnelQuestion;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;

/**
 * Datumsangabe (FB-011). Gespeichert wird immer ISO-8601 (Y-m-d), unabhaengig
 * davon, wie der Endkunde es eingegeben hat.
 */
class DateType extends BaseQuestionType
{
    protected function typeRules(FunnelQuestion $question): array
    {
        return ['date'];
    }

    public function normalize(mixed $value, FunnelQuestion $question): mixed
    {
        $value = parent::normalize($value, $question);

        if (! is_string($value)) {
            return $value;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (InvalidFormatException) {
            return $value;
        }
    }
}
