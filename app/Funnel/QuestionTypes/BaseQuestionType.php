<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

use App\Constants\QuestionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Gemeinsames Verhalten aller Fragetypen (FB-011).
 *
 * Typ und Blade-Komponente ergeben sich aus dem Klassennamen
 * (PhoneType -> phone -> funnel.questions.phone), damit ein neuer Typ nichts
 * ausser seiner eigenen Klasse braucht. Regeln entstehen aus drei Teilen:
 * Pflichtangabe, typeigene Regeln und die am Funnel hinterlegten Zusatzregeln
 * aus funnel_questions.validation.
 */
abstract class BaseQuestionType implements QuestionTypeHandler
{
    public function type(): QuestionType
    {
        return QuestionType::from(Str::snake(Str::beforeLast(class_basename($this), 'Type')));
    }

    /**
     * @return list<string|ValidationRule>
     */
    public function rules(QuestionDefinition $question): array
    {
        $rules = [$question->isAnswerRequired() && $this->expectsAnswer() ? 'required' : 'nullable'];

        return array_values(array_unique([
            ...$rules,
            ...$this->typeRules($question),
            ...$this->configuredRules($question),
        ], SORT_REGULAR));
    }

    public function normalize(mixed $value, QuestionDefinition $question): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }

    public function render(): string
    {
        return 'funnel.questions.'.$this->type()->value;
    }

    public function expectsAnswer(): bool
    {
        return true;
    }

    /**
     * Regeln, die sich allein aus dem Fragetyp ergeben.
     *
     * @return list<string|ValidationRule>
     */
    abstract protected function typeRules(QuestionDefinition $question): array;

    /**
     * Zusatzregeln, die am Funnel gepflegt sind (funnel_questions.validation),
     * etwa min/max fuer Zahlen oder eine maximale Laenge.
     *
     * @return list<string>
     */
    protected function configuredRules(QuestionDefinition $question): array
    {
        $rules = [];

        foreach ($question->configuredValidation() as $rule => $parameter) {
            if (is_int($rule)) {
                $rules[] = (string) $parameter;

                continue;
            }

            $rules[] = $parameter === null || $parameter === true
                ? $rule
                : $rule.':'.(is_array($parameter) ? implode(',', $parameter) : $parameter);
        }

        return $rules;
    }

    /**
     * Zulaessige Werte einer Auswahlfrage, aus den gepflegten Optionen.
     *
     * @return list<string>
     */
    protected function optionValues(QuestionDefinition $question): array
    {
        return $question->answerOptionValues();
    }
}
