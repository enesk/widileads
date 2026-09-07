<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\SessionEventType;
use App\Funnel\Snapshots\FunnelSnapshot;
use App\Models\Funnel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Trichter eines Funnels (FB-070).
 *
 * Beantwortet die eine Frage, die ein Betreiber an seine Strecke hat: Wo
 * springen die Leute ab? Gerechnet wird ausschliesslich in der Datenbank --
 * die Ereignisse einer Sitzung sind schnell fuenfstellig, und eine Auswertung,
 * die sie in PHP zaehlt, ist nach dem ersten erfolgreichen Funnel unbrauchbar.
 *
 * Gelesen werden die Ereignisse aus FB-021. Sie haengen an der Sitzung, die
 * Sitzung an der Funnel-Version -- ein Funnel wird also ueber alle seine
 * veroeffentlichten Fassungen hinweg ausgewertet. Das ist gewollt: Der
 * Betreiber will wissen, wie seine Strecke laeuft, nicht wie Fassung 3 lief.
 */
class FunnelConversionReport
{
    /**
     * Der vollstaendige Trichter eines Funnels im Zeitraum.
     *
     * @return array{
     *     views: int,
     *     submissions: int,
     *     abandoned: int,
     *     completion_rate: float,
     *     steps: list<array{position: int, title: string, views: int, completions: int, drop_offs: int, drop_off_rate: float}>
     * }
     */
    public function for(Funnel $funnel, ?Carbon $from = null, ?Carbon $until = null): array
    {
        $sessions = $this->sessionTotals($funnel, $from, $until);
        $stepCounts = $this->stepCounts($funnel, $from, $until);

        $steps = [];

        foreach ($this->stepTitles($funnel) as $position => $title) {
            $views = $stepCounts[$position][SessionEventType::STEP_VIEW->value] ?? 0;
            $completions = $stepCounts[$position][SessionEventType::STEP_COMPLETE->value] ?? 0;

            $steps[] = [
                'position' => $position,
                'title' => $title,
                'views' => $views,
                'completions' => $completions,
                // Wer den Schritt gesehen, aber nicht abgeschlossen hat.
                'drop_offs' => max(0, $views - $completions),
                'drop_off_rate' => $this->rate($views - $completions, $views),
            ];
        }

        return [
            'views' => $sessions['views'],
            'submissions' => $sessions['submissions'],
            'abandoned' => $sessions['abandoned'],
            'completion_rate' => $this->rate($sessions['submissions'], $sessions['views']),
            'steps' => $steps,
        ];
    }

    /**
     * Anteil in Prozent, auf eine Nachkommastelle.
     *
     * Ohne Grundgesamtheit gibt es keine Quote -- dann null, nicht "unendlich".
     */
    private function rate(int $part, int $total): float
    {
        return $total === 0 ? 0 : round($part / $total * 100, 1);
    }

    /**
     * Aufrufe, Abschluesse und Abbrueche -- je Sitzung, nicht je Ereignis.
     *
     * @return array{views: int, submissions: int, abandoned: int}
     */
    private function sessionTotals(Funnel $funnel, ?Carbon $from, ?Carbon $until): array
    {
        $row = $this->sessionQuery($funnel, $from, $until)
            ->selectRaw('count(*) as views')
            ->selectRaw('count(public_sessions.completed_at) as submissions')
            ->selectRaw('count(public_sessions.abandoned_at) as abandoned')
            ->first();

        return [
            'views' => (int) ($row->views ?? 0),
            'submissions' => (int) ($row->submissions ?? 0),
            'abandoned' => (int) ($row->abandoned ?? 0),
        ];
    }

    /**
     * Ereigniszahlen je Schrittposition und Ereignisart.
     *
     * @return array<int, array<string, int>>
     */
    private function stepCounts(Funnel $funnel, ?Carbon $from, ?Carbon $until): array
    {
        $rows = $this->sessionQuery($funnel, $from, $until)
            ->join('session_events', 'session_events.session_id', '=', 'public_sessions.id')
            ->whereIn('session_events.type', [
                SessionEventType::STEP_VIEW->value,
                SessionEventType::STEP_COMPLETE->value,
            ])
            ->whereNotNull('session_events.step_position')
            ->groupBy('session_events.step_position', 'session_events.type')
            ->selectRaw('session_events.step_position as position')
            ->selectRaw('session_events.type as type')
            // Je Sitzung einmal zaehlen: Wer einen Schritt zweimal ansieht,
            // ist trotzdem nur ein Besucher.
            ->selectRaw('count(distinct public_sessions.id) as total')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row->position][(string) $row->type] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @return Builder
     */
    private function sessionQuery(Funnel $funnel, ?Carbon $from, ?Carbon $until)
    {
        $query = DB::table('public_sessions')
            ->join('funnel_versions', 'funnel_versions.id', '=', 'public_sessions.funnel_version_id')
            ->where('funnel_versions.funnel_id', $funnel->getKey());

        if ($from !== null) {
            $query->where('public_sessions.started_at', '>=', $from);
        }

        if ($until !== null) {
            $query->where('public_sessions.started_at', '<=', $until);
        }

        return $query;
    }

    /**
     * Die Schritte der aktuell veroeffentlichten Fassung, mit ihren
     * Beschriftungen.
     *
     * @return array<int, string>
     */
    private function stepTitles(Funnel $funnel): array
    {
        $snapshot = $funnel->currentVersion?->snapshot;

        if (! is_array($snapshot)) {
            return [];
        }

        $titles = [];

        foreach (FunnelSnapshot::fromArray($snapshot)->steps as $step) {
            $titles[$step->position] = $step->title;
        }

        return $titles;
    }
}
