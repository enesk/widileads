<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Die Verzweigungsregeln eines Funnels fuehren im Kreis (FB-012).
 *
 * Der StepResolver bricht ab, sobald er mehr Schritte durchlaufen hat als der
 * Funnel ueberhaupt hergibt -- sonst haenge der Endkunde in einer Schleife.
 */
class FunnelStepCycleException extends RuntimeException
{
    public static function forStep(int $stepPosition, int $maxVisits): self
    {
        return new self(__('funnel.condition.errors.cycle_detected', [
            'step' => $stepPosition,
            'max' => $maxVisits,
        ]));
    }
}
