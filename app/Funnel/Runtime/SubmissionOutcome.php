<?php

declare(strict_types=1);

namespace App\Funnel\Runtime;

use App\Funnel\Snapshots\ResultSnapshot;

/**
 * Ergebnis einer abgeschickten Anfrage (FB-026).
 *
 * `accepted` ist falsch, wenn das Rate-Limit gegriffen hat -- der einzige Fall,
 * in dem eine Einreichung aufgehalten wird. Honeypot und Zeitfalle stehen in
 * den Signalen, halten die Anfrage aber nicht auf (FB-023).
 */
class SubmissionOutcome
{
    public function __construct(
        public readonly bool $accepted,
        public readonly ?ResultSnapshot $result,
        public readonly int $score,
        public readonly SpamAssessment $assessment,
    ) {}
}
