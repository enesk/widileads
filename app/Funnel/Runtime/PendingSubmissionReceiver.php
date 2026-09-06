<?php

declare(strict_types=1);

namespace App\Funnel\Runtime;

use App\Dto\FunnelSubmissionData;
use Illuminate\Support\Facades\Log;

/**
 * Platzhalter, bis FB-031 den Lead anlegt (FB-020).
 *
 * Legt bewusst nichts an: Die leads-Tabelle gehoert FB-030/FB-031. Damit in der
 * Zwischenzeit keine Einreichung unbemerkt verschwindet, wird jede uebergebene
 * Einreichung protokolliert -- ohne Antwortinhalte, weil dort Kontaktdaten
 * stehen koennen.
 */
class PendingSubmissionReceiver implements SubmissionReceiver
{
    public function receive(FunnelSubmissionData $submission): void
    {
        Log::info('Funnel-Einreichung ohne Empfaenger (FB-031 fehlt noch).', [
            'public_token' => $submission->publicToken,
            'funnel_version_id' => $submission->funnelVersionId,
            'score' => $submission->score,
            'result_key' => $submission->resultKey,
            'answered_fields' => array_keys($submission->answers),
        ]);
    }
}
