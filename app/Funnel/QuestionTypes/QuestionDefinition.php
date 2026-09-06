<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

use App\Constants\QuestionType;

/**
 * Was ein Fragetyp-Handler ueber "seine" Frage wissen muss (FB-020).
 *
 * Zwei Quellen liefern dieselbe Frage: das Eloquent-Model FunnelQuestion im
 * Builder und QuestionSnapshot in der oeffentlichen Auslieferung. Damit
 * Validierung und Normalisierung in beiden Faellen identisch laufen -- und die
 * Runtime trotzdem ausschliesslich Snapshots liest -- sprechen die Handler gegen
 * dieses Interface statt gegen das Model.
 */
interface QuestionDefinition
{
    public function questionType(): QuestionType;

    public function questionFieldKey(): string;

    public function questionLabel(): string;

    public function isAnswerRequired(): bool;

    /**
     * Zusatzregeln, die am Funnel gepflegt sind (funnel_questions.validation).
     *
     * @return array<string, mixed>
     */
    public function configuredValidation(): array;

    /**
     * Zulaessige Werte einer Auswahlfrage.
     *
     * @return list<string>
     */
    public function answerOptionValues(): array;
}
