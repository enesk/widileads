<?php

declare(strict_types=1);

namespace App\Funnel\Results;

use App\Funnel\Scoring\ScoreCalculator;
use App\Funnel\Snapshots\FunnelSnapshot;
use App\Funnel\Snapshots\ResultSnapshot;

/**
 * Waehlt den Ergebnis-Screen zu einer Punktzahl (FB-013).
 *
 * Beispiel Pfotencheck: 0-3 "geringes Risiko", 4-7 "erhoehtes Risiko",
 * ab 8 "hohes Risiko". Die Bereiche sind beidseitig einschliessend.
 *
 * Passt kein Bereich, liefert der Resolver null statt zu raten -- ein falsch
 * zugeordnetes Ergebnis waere schlimmer als gar keines. Dass so ein Fall
 * gar nicht erst entsteht, sichert der ResultRangeValidator beim Publish
 * (FB-014) ab.
 */
class ResultResolver
{
    public function __construct(private readonly ScoreCalculator $scoreCalculator) {}

    public function resolve(FunnelSnapshot $snapshot, int $score): ?ResultSnapshot
    {
        foreach ($snapshot->results as $result) {
            if ($result->covers($score)) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Ergebnis direkt aus den Antworten -- rechnet die Punktzahl selbst aus.
     *
     * @param  array<string, mixed>  $answers
     */
    public function resolveForAnswers(FunnelSnapshot $snapshot, array $answers): ?ResultSnapshot
    {
        return $this->resolve($snapshot, $this->scoreCalculator->calculate($snapshot, $answers));
    }
}
