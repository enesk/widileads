<?php

declare(strict_types=1);

namespace App\Funnel\Snapshots;

use App\Constants\QuestionType;
use App\Funnel\QuestionTypes\QuestionDefinition;

/**
 * Eine Frage aus dem Funnel-Snapshot (FB-013).
 */
class QuestionSnapshot implements QuestionDefinition
{
    /**
     * @param  list<OptionSnapshot>  $options  nach Position sortiert
     * @param  array<string, mixed>  $validation
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $fieldKey,
        public readonly string $type,
        public readonly string $label,
        public readonly ?string $helpText,
        public readonly bool $required,
        public readonly int $position,
        public readonly array $options,
        public readonly array $validation = [],
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $question
     */
    public static function fromArray(array $question): self
    {
        $options = array_map(
            static fn (array $option): OptionSnapshot => OptionSnapshot::fromArray($option),
            array_values((array) ($question['options'] ?? [])),
        );

        usort($options, static fn (OptionSnapshot $a, OptionSnapshot $b): int => $a->position <=> $b->position);

        return new self(
            fieldKey: (string) ($question['field_key'] ?? ''),
            type: (string) ($question['type'] ?? ''),
            label: (string) ($question['label'] ?? ''),
            helpText: isset($question['help_text']) ? (string) $question['help_text'] : null,
            required: (bool) ($question['required'] ?? false),
            position: (int) ($question['position'] ?? 0),
            options: $options,
            validation: (array) ($question['validation'] ?? []),
            meta: (array) ($question['meta'] ?? []),
        );
    }

    public function questionType(): QuestionType
    {
        return QuestionType::from($this->type);
    }

    public function questionFieldKey(): string
    {
        return $this->fieldKey;
    }

    public function questionLabel(): string
    {
        return $this->label;
    }

    public function isAnswerRequired(): bool
    {
        return $this->required;
    }

    /**
     * @return array<string, mixed>
     */
    public function configuredValidation(): array
    {
        return $this->validation;
    }

    /**
     * @return list<string>
     */
    public function answerOptionValues(): array
    {
        return array_values(array_map(
            static fn (OptionSnapshot $option): string => $option->value,
            $this->options,
        ));
    }

    /**
     * Punkte, die eine gegebene Antwort auf diese Frage bringt.
     *
     * Bei einer Mehrfachauswahl summieren sich die Punkte aller angekreuzten
     * Optionen. Fragen ohne Optionen (Freitext, Zahl, Kontaktfelder) tragen
     * nichts zur Punktzahl bei.
     */
    public function pointsFor(mixed $answer): int
    {
        if ($this->options === [] || $answer === null) {
            return 0;
        }

        $answers = is_array($answer) ? array_values($answer) : [$answer];
        $points = 0;

        foreach ($answers as $given) {
            foreach ($this->options as $option) {
                if ($option->matches($given)) {
                    $points += $option->points();

                    break;
                }
            }
        }

        return $points;
    }

    /**
     * Hoechste erreichbare Punktzahl dieser Frage -- bei Mehrfachauswahl die
     * Summe aller positiven Optionen, sonst die beste einzelne Option.
     */
    public function maximumPoints(): int
    {
        if ($this->options === []) {
            return 0;
        }

        if ($this->type === 'multi_choice') {
            return array_sum(array_map(
                static fn (OptionSnapshot $option): int => max(0, $option->points()),
                $this->options,
            ));
        }

        return max(array_map(static fn (OptionSnapshot $option): int => $option->points(), $this->options));
    }
}
