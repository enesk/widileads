<?php

declare(strict_types=1);

namespace App\Funnel\Runtime;

use App\Funnel\Snapshots\ResultSnapshot;
use App\Funnel\Snapshots\StepSnapshot;

/**
 * Wie es nach einem Schritt weitergeht (FB-026).
 *
 * Der Rueckgabewert von FunnelRunService::submitStep(): entweder der naechste
 * Schritt, oder das Ergebnis, oder das Ende der Strecke. Beide Oberflaechen --
 * die Livewire-Strecke und die Headless-API -- lesen dieselbe Antwort, damit
 * ein fremdes Frontend denselben Weg nimmt wie unser eigenes.
 */
class StepOutcome
{
    private function __construct(
        public readonly string $phase,
        public readonly ?StepSnapshot $step = null,
        public readonly ?ResultSnapshot $result = null,
        public readonly int $score = 0,
    ) {}

    public static function nextStep(StepSnapshot $step): self
    {
        return new self('step', step: $step);
    }

    public static function result(ResultSnapshot $result, int $score): self
    {
        return new self('result', result: $result, score: $score);
    }

    public static function finished(?ResultSnapshot $result, int $score): self
    {
        return new self('done', result: $result, score: $score);
    }

    public function isFinished(): bool
    {
        return $this->phase === 'done';
    }
}
