<?php

declare(strict_types=1);

namespace App\Funnel\Snapshots;

/**
 * Ein Schritt aus dem Funnel-Snapshot (FB-012).
 */
class StepSnapshot
{
    /**
     * @param  list<array<string, mixed>>  $questions  nach Position sortiert
     */
    public function __construct(
        public readonly int $position,
        public readonly string $title,
        public readonly ?string $description,
        public readonly array $questions,
    ) {}

    /**
     * @param  array<string, mixed>  $step
     */
    public static function fromArray(array $step): self
    {
        $questions = array_values((array) ($step['questions'] ?? []));

        usort($questions, static fn (array $a, array $b): int => ((int) ($a['position'] ?? 0)) <=> ((int) ($b['position'] ?? 0)));

        return new self(
            position: (int) ($step['position'] ?? 0),
            title: (string) ($step['title'] ?? ''),
            description: isset($step['description']) ? (string) $step['description'] : null,
            questions: $questions,
        );
    }

    /**
     * Feldschluessel aller Fragen dieses Schritts.
     *
     * @return list<string>
     */
    public function fieldKeys(): array
    {
        return array_values(array_map(
            static fn (array $question): string => (string) ($question['field_key'] ?? ''),
            $this->questions,
        ));
    }
}
