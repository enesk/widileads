<?php

declare(strict_types=1);

namespace App\Funnel\Scoring;

use App\Funnel\Snapshots\FunnelSnapshot;
use App\Funnel\Snapshots\QuestionSnapshot;

/**
 * Berechnet die Punktzahl einer Funnel-Einreichung (FB-013).
 *
 * Punkte haengen an den Antwortoptionen (funnel_options.score, seit FB-010).
 * Bei einer Mehrfachauswahl summieren sich die Punkte aller angekreuzten
 * Optionen; Fragen ohne Optionen -- Freitext, Zahl, Kontaktfelder -- tragen
 * nichts bei.
 *
 * Gearbeitet wird wie beim StepResolver ausschliesslich auf dem Snapshot: keine
 * Datenbankabfrage, damit Runtime, API und Test dieselbe Rechnung anstellen.
 */
class ScoreCalculator
{
    /**
     * Gesamtpunktzahl fuer einen Satz Antworten (field_key => Wert).
     *
     * @param  array<string, mixed>  $answers
     */
    public function calculate(FunnelSnapshot $snapshot, array $answers): int
    {
        $score = 0;

        foreach ($snapshot->questions() as $question) {
            $score += $question->pointsFor($answers[$question->fieldKey] ?? null);
        }

        return $score;
    }

    /**
     * Punkte je Frage -- fuer die Anzeige im Builder und zur Fehlersuche, wenn
     * eine Einreichung im falschen Ergebnis landet.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, int>
     */
    public function breakdown(FunnelSnapshot $snapshot, array $answers): array
    {
        $breakdown = [];

        foreach ($snapshot->questions() as $question) {
            $breakdown[$question->fieldKey] = $question->pointsFor($answers[$question->fieldKey] ?? null);
        }

        return $breakdown;
    }

    /**
     * Hoechste ueberhaupt erreichbare Punktzahl dieses Funnels. Der
     * ResultRangeValidator prueft damit, ob die Ergebnisbereiche den ganzen
     * moeglichen Bereich abdecken.
     */
    public function maximumScore(FunnelSnapshot $snapshot): int
    {
        return array_sum(array_map(
            static fn (QuestionSnapshot $question): int => $question->maximumPoints(),
            $snapshot->questions(),
        ));
    }
}
