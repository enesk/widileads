<?php

declare(strict_types=1);

namespace App\Funnel\Results;

use App\Funnel\Scoring\ScoreCalculator;
use App\Funnel\Snapshots\FunnelSnapshot;
use App\Funnel\Snapshots\ResultSnapshot;

/**
 * Prueft die Ergebnisbereiche eines Funnels auf Lueckenlosigkeit und
 * Ueberschneidungsfreiheit (FB-013).
 *
 * Eigenstaendig aufrufbar: Publish (FB-014) und der Ergebnis-Editor (FB-016)
 * rufen validate() auf und zeigen die Meldungen an -- hier steckt bewusst keine
 * Publish-Logik.
 *
 * Geprueft wird gegen den tatsaechlich erreichbaren Punktebereich (0 bis zur
 * hoechsten erreichbaren Punktzahl). Ein Funnel, dessen Ergebnisse erst bei 4
 * beginnen, laesst jeden Endkunden mit 0 bis 3 Punkten ohne Ergebnis stehen --
 * genau das soll die Pruefung vor der Veroeffentlichung sichtbar machen.
 */
class ResultRangeValidator
{
    public function __construct(private readonly ScoreCalculator $scoreCalculator) {}

    public function validate(FunnelSnapshot $snapshot): ResultRangeReport
    {
        $results = $snapshot->results;

        // Ein Funnel ohne Ergebnis-Screens ist zulaessig: Er fuehrt direkt zum
        // Kontaktschritt. Ob Ergebnisse Pflicht sind, entscheidet Publish.
        if ($results === []) {
            return new ResultRangeReport;
        }

        $invalidRanges = [];
        $overlaps = [];

        foreach ($results as $result) {
            if ($result->minScore > $result->maxScore) {
                $invalidRanges[] = __('funnel.result.errors.invalid_range', [
                    'title' => $result->title,
                    'min' => $result->minScore,
                    'max' => $result->maxScore,
                ]);
            }
        }

        // $snapshot->results ist bereits nach Mindestpunktzahl sortiert.
        $previous = null;

        foreach ($results as $result) {
            if ($previous instanceof ResultSnapshot && $result->minScore <= $previous->maxScore) {
                $overlaps[] = __('funnel.result.errors.overlap', [
                    'first' => $previous->title,
                    'first_range' => $previous->range(),
                    'second' => $result->title,
                    'second_range' => $result->range(),
                ]);
            }

            if ($previous === null || $result->maxScore > $previous->maxScore) {
                $previous = $result;
            }
        }

        return new ResultRangeReport(
            overlaps: $overlaps,
            gaps: $this->findGaps($snapshot, $results),
            invalidRanges: $invalidRanges,
        );
    }

    /**
     * Punktzahlen zwischen 0 und der hoechsten erreichbaren Punktzahl, die von
     * keinem Bereich abgedeckt sind -- zusammengefasst zu Luecken.
     *
     * @param  list<ResultSnapshot>  $results
     * @return list<string>
     */
    private function findGaps(FunnelSnapshot $snapshot, array $results): array
    {
        $highestReachable = $this->scoreCalculator->maximumScore($snapshot);
        $highestConfigured = max(array_map(static fn (ResultSnapshot $r): int => $r->maxScore, $results));
        $upperBound = max($highestReachable, $highestConfigured);

        $gaps = [];
        $gapStart = null;

        for ($score = 0; $score <= $upperBound; $score++) {
            $covered = false;

            foreach ($results as $result) {
                if ($result->covers($score)) {
                    $covered = true;

                    break;
                }
            }

            if (! $covered) {
                $gapStart ??= $score;

                continue;
            }

            if ($gapStart !== null) {
                $gaps[] = $this->gapMessage($gapStart, $score - 1);
                $gapStart = null;
            }
        }

        if ($gapStart !== null) {
            $gaps[] = $this->gapMessage($gapStart, $upperBound);
        }

        return $gaps;
    }

    private function gapMessage(int $from, int $to): string
    {
        return $from === $to
            ? __('funnel.result.errors.gap_single', ['score' => $from])
            : __('funnel.result.errors.gap_range', ['from' => $from, 'to' => $to]);
    }
}
