<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\LeadState;
use App\Models\Tenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Zeitverlauf der Leads eines Betreibers (FB-071).
 *
 * Wie viele Anfragen kommen herein, und woher? Die Reihe laesst sich nach Tag,
 * Woche oder Monat bilden und nach einer Dimension aufschluesseln: Funnel,
 * Herkunft oder Zustand.
 *
 * Gerechnet wird in der Datenbank. Ein Betreiber mit fuenfstelligen Leadzahlen
 * soll dieselbe Seite in derselben Zeit bekommen wie einer mit dreistelligen.
 */
class LeadTimelineReport
{
    /**
     * Zeitraster der Reihe.
     */
    public const GRANULARITIES = ['day', 'week', 'month'];

    /**
     * Dimensionen, nach denen sich die Reihe aufschluesseln laesst.
     */
    public const BREAKDOWNS = ['none', 'funnel', 'origin', 'state'];

    /**
     * @param  array<string, mixed>  $filters
     * @return array{buckets: list<string>, series: list<array{label: string, values: array<string, int>, total: int}>, total: int}
     */
    public function for(Tenant $tenant, string $granularity, string $breakdown, array $filters = []): array
    {
        $granularity = in_array($granularity, self::GRANULARITIES, true) ? $granularity : 'day';
        $breakdown = in_array($breakdown, self::BREAKDOWNS, true) ? $breakdown : 'none';

        $rows = $this->rows($tenant, $granularity, $breakdown, $filters);

        $buckets = [];
        $series = [];

        foreach ($rows as $row) {
            $bucket = (string) $row->bucket;
            $label = $this->label($breakdown, $row);

            $buckets[$bucket] = true;
            $series[$label][$bucket] = (int) $row->total;
        }

        ksort($buckets);

        $bucketKeys = array_keys($buckets);
        $result = [];
        $total = 0;

        foreach ($series as $label => $values) {
            $seriesTotal = array_sum($values);
            $total += $seriesTotal;

            $result[] = [
                'label' => (string) $label,
                // Leere Zeitraeume erscheinen als Null, nicht als Luecke --
                // sonst liest sich eine Reihe mit Ausfaellen wie eine ohne.
                'values' => array_map(
                    static fn (string $bucket): int => $values[$bucket] ?? 0,
                    array_combine($bucketKeys, $bucketKeys),
                ),
                'total' => $seriesTotal,
            ];
        }

        usort($result, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return ['buckets' => $bucketKeys, 'series' => $result, 'total' => $total];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, stdClass>
     */
    private function rows(Tenant $tenant, string $granularity, string $breakdown, array $filters)
    {
        $query = DB::table('leads')
            ->leftJoin('funnels', 'funnels.id', '=', 'leads.funnel_id')
            ->where('leads.tenant_id', $tenant->getKey())
            ->selectRaw($this->bucketExpression($granularity).' as bucket')
            ->selectRaw('count(*) as total')
            ->groupBy('bucket')
            ->orderBy('bucket');

        $this->applyFilters($query, $filters);

        if ($breakdown !== 'none') {
            $column = $this->breakdownColumn($breakdown);

            $query->addSelect(DB::raw($column.' as series'))->groupBy(DB::raw($column));
        }

        return $query->get();
    }

    /**
     * @param  Builder  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters($query, array $filters): void
    {
        $funnelId = $filters['funnel_id'] ?? null;

        if (is_numeric($funnelId)) {
            $query->where('leads.funnel_id', (int) $funnelId);
        }

        $state = $filters['lead_state'] ?? null;

        if (is_string($state) && LeadState::tryFrom($state) !== null) {
            $query->where('leads.lead_state', $state);
        }

        $from = $filters['from'] ?? null;

        if ($from instanceof Carbon) {
            $query->where('leads.created_at', '>=', $from);
        }

        $until = $filters['until'] ?? null;

        if ($until instanceof Carbon) {
            $query->where('leads.created_at', '<=', $until);
        }
    }

    /**
     * Das Zeitraster als SQL-Ausdruck. Der Monat wird als `2026-09`
     * geschrieben, die Woche als `2026-KW36` -- beides sortiert sich als Text
     * richtig.
     */
    private function bucketExpression(string $granularity): string
    {
        return match ($granularity) {
            'week' => "date_format(leads.created_at, '%x-KW%v')",
            'month' => "date_format(leads.created_at, '%Y-%m')",
            default => 'date(leads.created_at)',
        };
    }

    private function breakdownColumn(string $breakdown): string
    {
        return match ($breakdown) {
            'funnel' => 'funnels.name',
            // Die Herkunft ist die Kampagnenquelle, sonst die einbettende Seite.
            'origin' => 'coalesce(leads.utm_source, leads.embed_origin)',
            default => 'leads.lead_state',
        };
    }

    private function label(string $breakdown, stdClass $row): string
    {
        if ($breakdown === 'none') {
            return __('timeline.all_leads');
        }

        $value = $row->series ?? null;

        if ($value === null || $value === '') {
            return __('timeline.unknown');
        }

        return $breakdown === 'state'
            ? (LeadState::tryFrom((string) $value)?->label() ?? (string) $value)
            : (string) $value;
    }
}
