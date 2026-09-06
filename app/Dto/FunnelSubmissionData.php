<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Eine abgeschlossene Funnel-Einreichung (FB-020, erweitert in FB-021).
 *
 * Das ist die Uebergabe an FB-031 (CreateLeadFromSession): alles, was dort
 * gebraucht wird, um einen Lead anzulegen -- und nichts darueber hinaus. Die
 * Werte sind bereits validiert und ueber die Fragetyp-Handler normalisiert
 * (Telefonnummern in E.164, E-Mail kleingeschrieben, Datum als ISO-8601).
 *
 * Der Verlauf steht bewusst NICHT hier: Seit FB-021 fuehren public_sessions und
 * session_events ihn, und zwar als einzige Quelle. Ein zweiter Verlauf im DTO
 * koennte davon abweichen -- zwei Wahrheiten ueber denselben Weg sind schlimmer
 * als eine. Wer den Weg braucht, liest PublicSession::visitedStepPositions()
 * oder direkt die Ereignisse; started_at und completed_at stehen an der Sitzung.
 *
 * Bewusst nicht enthalten: die IP-Adresse. Herkunftsdaten samt IP-Hash sind
 * FB-022.
 *
 * Die Spam-Signale aus FB-023 sind Beobachtungen, kein Urteil: Der Lead entsteht
 * auch bei Verdacht, mit lead_state = neu. Ob daraus ein ungueltiger Lead wird,
 * entscheidet der Pruefjob in FB-033.
 */
class FunnelSubmissionData
{
    /**
     * @param  string  $publicToken  oeffentlicher Token des Funnels (nie die ID)
     * @param  int  $funnelVersionId  Fassung, die der Endkunde gesehen hat
     * @param  int  $publicSessionId  Sitzung mit Antworten, Zeitstempeln und Verlauf
     * @param  array<string, mixed>  $answers  field_key => normalisierter Wert
     * @param  string|null  $resultKey  Ergebnisschluessel aus dem Snapshot ("0-3"), nicht die funnel_results-ID
     * @param  array<string, mixed>  $spamSignals  Beobachtungen aus FB-023, unbewertet
     * @param  int|null  $duplicateOfLeadId  moeglicher Vorgaenger mit denselben Kontaktdaten
     */
    public function __construct(
        public readonly string $publicToken,
        public readonly int $funnelVersionId,
        public readonly int $publicSessionId,
        public readonly array $answers,
        public readonly int $score,
        public readonly ?string $resultKey,
        public readonly array $spamSignals = [],
        public readonly ?int $duplicateOfLeadId = null,
    ) {}
}
