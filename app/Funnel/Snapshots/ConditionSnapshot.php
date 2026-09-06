<?php

declare(strict_types=1);

namespace App\Funnel\Snapshots;

use App\Constants\ConditionOperator;

/**
 * Eine Verzweigungsregel aus dem Funnel-Snapshot (FB-012).
 *
 * Verwiesen wird ueber Feldschluessel und Schrittposition, nicht ueber IDs --
 * der Snapshot soll ohne die Live-Tabellen lesbar bleiben.
 *
 * `sourceFieldKey` sagt, WAS geprueft wird, `evaluateAtStepPosition`, WANN
 * (FB-012a). Fehlt das Feld -- etwa in einem vor FB-012a veroeffentlichten
 * Snapshot --, setzt FunnelSnapshot beim Lesen den Schritt der Ausgangsfrage
 * ein; bestehende Funnels laufen unveraendert weiter.
 */
class ConditionSnapshot
{
    public function __construct(
        public readonly string $sourceFieldKey,
        public readonly ConditionOperator $operator,
        public readonly mixed $value,
        public readonly int $targetStepPosition,
        public readonly int $priority,
        public readonly ?int $evaluateAtStepPosition = null,
    ) {}

    /**
     * Dieselbe Regel mit gesetztem Auswertungsschritt -- gebraucht, wenn ein
     * aelterer Snapshot das Feld nicht traegt.
     */
    public function evaluatedAt(int $stepPosition): self
    {
        return new self(
            sourceFieldKey: $this->sourceFieldKey,
            operator: $this->operator,
            value: $this->value,
            targetStepPosition: $this->targetStepPosition,
            priority: $this->priority,
            evaluateAtStepPosition: $stepPosition,
        );
    }

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
            evaluateAtStepPosition: isset($condition['evaluate_at_step_position'])
                ? (int) $condition['evaluate_at_step_position']
                : null,
        );
    }
}
