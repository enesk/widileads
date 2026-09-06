<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

use App\Constants\QuestionType;
use App\Models\FunnelQuestion;

/**
 * Verhalten eines Fragetyps (FB-011).
 *
 * Ein Handler beantwortet drei Fragen zu seinem Typ: Wie wird die Antwort
 * validiert, wie wird sie vereinheitlicht, und welche Blade-Komponente stellt
 * die Frage dar. Fachlogik darueber hinaus gehoert nicht hierher.
 */
interface QuestionTypeHandler
{
    /**
     * Fragetyp, den dieser Handler bedient.
     */
    public function type(): QuestionType;

    /**
     * Laravel-Validierungsregeln fuer die Antwort auf diese Frage.
     *
     * @return list<string>
     */
    public function rules(FunnelQuestion $question): array;

    /**
     * Vereinheitlicht die eingegebene Antwort, bevor sie gespeichert wird.
     */
    public function normalize(mixed $value, FunnelQuestion $question): mixed;

    /**
     * Name der Blade-Komponente, die die Frage darstellt.
     */
    public function render(): string;

    /**
     * Erwartet dieser Typ ueberhaupt eine Antwort? Ein Infoschritt nicht.
     */
    public function expectsAnswer(): bool;
}
