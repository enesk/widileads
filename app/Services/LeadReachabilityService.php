<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\LeadContactStatus;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Die Erreichbarkeitsquote je Kaeufer (FB-085).
 *
 * Beantwortet die Frage, die im Streitfall zaehlt: Wie oft bekommt dieser
 * Kaeufer seine Leads nicht ans Telefon -- und faellt er damit aus dem Rahmen?
 *
 * **Auffaellig heisst nicht schuldig.** Eine hohe Quote kann heissen, dass ein
 * Kaeufer schlechte Leads bekommen hat, dass er zu spaet anruft oder dass er
 * gar nicht ernsthaft anruft. Die Uebersicht sagt nur, wo hinzusehen sich
 * lohnt; die Entscheidung trifft ein Mensch. Deshalb aendert diese Klasse
 * nichts -- sie liest ausschliesslich.
 *
 * Gerechnet wird in einer Abfrage mit bedingten Summen, nicht ueber gefilterte
 * Sammlungen: Die Zahlen sollen auch bei sechsstelligen Kaufmengen in einem
 * Seitenaufruf stehen.
 */
class LeadReachabilityService
{
    /**
     * Kennzahlen je Kaeufer, auffaellige zuerst.
     *
     * Die Bezugsgroesse der Quote sind die entschiedenen Leads
     * (billable + unreachable), nicht alle gekauften: Ein Lead, dessen Frist
     * noch laeuft, ist weder erreicht noch nicht erreicht. Er steht in
     * `leads_total` und in `open`, aber nicht im Nenner.
     *
     * @return array{
     *     rows: list<array{
     *         tenant: Tenant|null,
     *         buyer_tenant_id: int,
     *         leads_total: int,
     *         billable: int,
     *         unreachable: int,
     *         open: int,
     *         decided: int,
     *         unreachable_rate: float,
     *         deviation_points: float,
     *         flagged: bool
     *     }>,
     *     average_rate: float,
     *     leads_total: int,
     *     billable: int,
     *     unreachable: int,
     *     decided: int
     * }
     */
    public function summary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $rows = $this->aggregate($from, $to);

        $totalUnreachable = array_sum(array_column($rows, 'unreachable'));
        $totalBillable = array_sum(array_column($rows, 'billable'));
        $totalDecided = array_sum(array_column($rows, 'decided'));

        $average = $totalDecided === 0 ? 0.0 : $totalUnreachable / $totalDecided;

        $threshold = (float) config('lead_calls.unreachable_rate_flag_points');

        foreach ($rows as $index => $row) {
            $deviation = ($row['unreachable_rate'] - $average) * 100;

            $rows[$index]['deviation_points'] = $deviation;
            $rows[$index]['flagged'] = $deviation > $threshold;
        }

        // Auffaellige zuerst, danach die groessten Kaeufer -- so steht oben,
        // was Aufmerksamkeit braucht.
        usort($rows, static function (array $a, array $b): int {
            return [$b['flagged'], $b['deviation_points'], $b['leads_total']]
                <=> [$a['flagged'], $a['deviation_points'], $a['leads_total']];
        });

        return [
            'rows' => $rows,
            'average_rate' => $average,
            'leads_total' => array_sum(array_column($rows, 'leads_total')),
            'billable' => $totalBillable,
            'unreachable' => $totalUnreachable,
            'decided' => $totalDecided,
        ];
    }

    /**
     * Eine Abfrage, eine Zeile je Kaeufer.
     *
     * Der Zeitraum greift auf `leads.resolved_at`: Gefragt ist, was in diesem
     * Zeitraum entschieden wurde. Leads ohne Entscheidung fallen damit aus dem
     * gefilterten Bild heraus -- ohne Zeitraum sind sie als `open` dabei.
     *
     * @return list<array<string, mixed>>
     */
    private function aggregate(?Carbon $from, ?Carbon $to): array
    {
        $status = static fn (LeadContactStatus $case): string => sprintf(
            "SUM(CASE WHEN leads.contact_status = '%s' THEN 1 ELSE 0 END)",
            $case->value,
        );

        $query = LeadPurchase::query()
            ->join('leads', 'leads.id', '=', 'lead_purchases.lead_id')
            ->groupBy('lead_purchases.buyer_tenant_id')
            ->select('lead_purchases.buyer_tenant_id')
            ->selectRaw('COUNT(*) as leads_total')
            ->selectRaw($status(LeadContactStatus::BILLABLE).' as billable')
            ->selectRaw($status(LeadContactStatus::UNREACHABLE).' as unreachable')
            ->selectRaw($status(LeadContactStatus::OPEN).' as still_open');

        if ($from instanceof Carbon) {
            $query->where('leads.resolved_at', '>=', $from);
        }

        if ($to instanceof Carbon) {
            $query->where('leads.resolved_at', '<=', $to);
        }

        $aggregates = $query->get();

        $buyers = Tenant::query()
            ->whereIn('id', $aggregates->pluck('buyer_tenant_id')->all())
            ->get()
            ->keyBy(static fn (Tenant $tenant): int => (int) $tenant->getKey());

        $rows = [];

        foreach ($aggregates as $aggregate) {
            $billable = (int) $aggregate->getAttribute('billable');
            $unreachable = (int) $aggregate->getAttribute('unreachable');
            $decided = $billable + $unreachable;
            $buyerId = (int) $aggregate->getAttribute('buyer_tenant_id');

            $rows[] = [
                'tenant' => $buyers->get($buyerId),
                'buyer_tenant_id' => $buyerId,
                'leads_total' => (int) $aggregate->getAttribute('leads_total'),
                'billable' => $billable,
                'unreachable' => $unreachable,
                'open' => (int) $aggregate->getAttribute('still_open'),
                'decided' => $decided,
                'unreachable_rate' => $decided === 0 ? 0.0 : $unreachable / $decided,
                'deviation_points' => 0.0,
                'flagged' => false,
            ];
        }

        return $rows;
    }
}
