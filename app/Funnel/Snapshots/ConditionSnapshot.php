<?php

declare(strict_types=1);

namespace App\Funnel\Snapshots;

use App\Constants\ConditionOperator;

/**
 * Eine Verzweigungsregel aus dem Funnel-Snapshot (FB-012).
 *
 * Verwiesen wird ueber Feldschluessel und Schrittposition, nicht ueber IDs --
 * der Snapshot soll ohne die Live-Tabellen lesbar bleiben.
 */
class ConditionSnapshot
{
    public function __construct(
        public readonly string $sourceFieldKey,
        public readonly ConditionOperator $operator,
        public readonly mixed $value,
        public readonly int $targetStepPosition,
        public readonly int $priority,
    ) {}

    /**
     * @param  array<string, mixed>  $condition
     */
    public static function fromArray(array $condition): self
    {
        return new self(
            sourceFieldKey: (string) ($condition['source_field_key'] ?? ''),
            operator: ConditionOperator::from((string) ($condition['operator'] ?? '')),
            value: $condition['value'] ?? null,
            targetStepPosition: (int) ($condition['target_step_position'] ?? 0),
            priority: (int) ($condition['priority'] ?? 0),
        );
    }
}
