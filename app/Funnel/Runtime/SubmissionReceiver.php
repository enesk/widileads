<?php

declare(strict_types=1);

namespace App\Funnel\Runtime;

use App\Dto\FunnelSubmissionData;

/**
 * Nimmt eine abgeschlossene Funnel-Einreichung entgegen (FB-020).
 *
 * Die Runtime kennt nur dieses Interface. FB-031 (CreateLeadFromSession) bindet
 * hier seine Umsetzung ein und legt daraus den Lead an -- ohne dass die Runtime
 * angefasst werden muss.
 */
interface SubmissionReceiver
{
    public function receive(FunnelSubmissionData $submission): void;
}
