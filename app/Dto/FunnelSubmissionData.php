<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Eine abgeschlossene Funnel-Einreichung (FB-020).
 *
 * Das ist die Uebergabe an FB-031 (CreateLeadFromSession): alles, was dort
 * gebraucht wird, um einen Lead anzulegen -- und nichts darueber hinaus. Die
 * Werte sind bereits validiert und ueber die Fragetyp-Handler normalisiert
 * (Telefonnummern in E.164, E-Mail kleingeschrieben, Datum als ISO-8601).
 *
 * Bewusst NICHT enthalten: die IP-Adresse. Herkunftsdaten samt IP-Hash sind
 * FB-022, und die Roh-IP verlaesst ohnehin nie den AuditLogger bzw. den
 * Hash-Dienst.
 */
class FunnelSubmissionData
{
    /**
     * @param  string  $publicToken  oeffentlicher Token des Funnels (nie die ID)
     * @param  int  $funnelVersionId  Fassung, die der Endkunde gesehen hat
     * @param  array<string, mixed>  $answers  field_key => normalisierter Wert
     * @param  list<int>  $visitedStepPositions  tatsaechlich durchlaufener Weg
     * @param  string|null  $resultKey  Ergebnisschluessel aus dem Snapshot ("0-3"), nicht die funnel_results-ID
     */
    public function __construct(
        public readonly string $publicToken,
        public readonly int $funnelVersionId,
        public readonly array $answers,
        public readonly int $score,
        public readonly ?string $resultKey,
        public readonly array $visitedStepPositions,
        public readonly ?string $startedAt = null,
        public readonly ?string $submittedAt = null,
    ) {}
}
