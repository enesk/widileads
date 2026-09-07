<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\CreditLedgerType;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Umsatzuebersicht eines Betreibers (FB-072).
 *
 * Was haben die eigenen Funnels eingebracht, und was ist davon wieder
 * abgegangen? Aufgeschluesselt je Funnel und je Kaeufer.
 *
 * Gerechnet wird durchgaengig in der kleinsten Waehrungseinheit -- Cent, nie
 * Gleitkomma. Ein Rundungsfehler in einer Umsatzuebersicht faellt niemandem
 * auf, bis die Summe nicht mehr zur Buchhaltung passt.
 *
 * Die Waehrung ist Teil der Gruppierung. In der Praxis gibt es genau eine,
 * aber eine Summe ueber zwei Waehrungen waere schlicht falsch, und das soll
 * nicht davon abhaengen, dass es nie vorkommt.
 */
class OperatorRevenueReport
{
    /**
     * @return array{
     *     by_funnel: list<array{label: string, currency: string, sold: int, revenue_cents: int, refunds: int, refunded_cents: int, net_cents: int}>,
     *     by_buyer: list<array{label: string, currency: string, sold: int, revenue_cents: int, refunds: int, refunded_cents: int, net_cents: int}>,
     *     totals: list<array{currency: string, sold: int, revenue_cents: int, refunds: int, refunded_cents: int, net_cents: int}>
     * }
     */
    public function for(Tenant $tenant, ?Carbon $from = null, ?Carbon $until = null): array
    {
        $refunds = $this->refundsByPurchase($tenant, $from, $until);

        return [
            'by_funnel' => $this->group($tenant, $from, $until, 'funnels.name', $refunds),
            'by_buyer' => $this->group($tenant, $from, $until, 'buyers.name', $refunds),
            'totals' => $this->group($tenant, $from, $until, null, $refunds),
        ];
    }

    /**
     * Eine Gruppierungsebene der Uebersicht.
     *
     * Der Name der Gruppe wird als Spalte gelesen und erst in PHP ersetzt,
     * wenn er fehlt: Ein `coalesce` mit gebundenem Wert steht in SELECT und
     * GROUP BY als zwei verschiedene Ausdruecke da und faellt MySQL im Modus
     * `only_full_group_by` auf die Fuesse.
     *
     * @param  array<int, int>  $refunds  Kauf-ID => gutgeschriebene Cent
     * @return list<array<string, mixed>>
     */
    private function group(Tenant $tenant, ?Carbon $from, ?Carbon $until, ?string $labelColumn, array $refunds): array
    {
        $rows = $this->purchaseQuery($tenant, $from, $until)
            ->when(
                $labelColumn !== null,
                fn ($query) => $query->selectRaw($labelColumn.' as label')->groupBy($labelColumn),
            )
            ->selectRaw('lead_purchases.currency as currency')
            ->selectRaw('count(*) as sold')
            ->selectRaw('sum(lead_purchases.price_cents) as revenue_cents')
            ->selectRaw('group_concat(lead_purchases.id) as purchase_ids')
            ->groupBy('lead_purchases.currency')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            [$refundCount, $refundedCents] = $this->refundTotals($row, $refunds);
            $revenue = (int) $row->revenue_cents;

            $entry = [
                'currency' => (string) $row->currency,
                'sold' => (int) $row->sold,
                'revenue_cents' => $revenue,
                'refunds' => $refundCount,
                'refunded_cents' => $refundedCents,
                'net_cents' => $revenue - $refundedCents,
            ];

            if ($labelColumn !== null) {
                $label = $row->label ?? null;

                $entry = [
                    'label' => $label === null || $label === '' ? __('operator_revenue.without_funnel') : (string) $label,
                ] + $entry;
            }

            $result[] = $entry;
        }

        usort($result, static fn (array $a, array $b): int => $b['net_cents'] <=> $a['net_cents']);

        return $result;
    }

    /**
     * @param  array<int, int>  $refunds
     * @return array{0: int, 1: int}
     */
    private function refundTotals(stdClass $row, array $refunds): array
    {
        $ids = array_filter(explode(',', (string) ($row->purchase_ids ?? '')));

        $count = 0;
        $cents = 0;

        foreach ($ids as $id) {
            $refunded = $refunds[(int) $id] ?? null;

            if ($refunded === null) {
                continue;
            }

            $count++;
            $cents += $refunded;
        }

        return [$count, $cents];
    }

    /**
     * Gutschriften je Kauf.
     *
     * Eine Gutschrift entsteht nach einer anerkannten Reklamation (FB-058) und
     * traegt den Kauf als Beleg. Gebucht wird sie in Guthaben, nicht in Cent --
     * fuer die Umsatzuebersicht zaehlt aber der Betrag, den der Kauf gekostet
     * hat: Genau der geht dem Betreiber wieder ab.
     *
     * @return array<int, int> Kauf-ID => gutgeschriebene Cent
     */
    private function refundsByPurchase(Tenant $tenant, ?Carbon $from, ?Carbon $until): array
    {
        $rows = $this->purchaseQuery($tenant, $from, $until)
            ->join('credit_ledger', function ($join): void {
                $join->on('credit_ledger.reference_id', '=', 'lead_purchases.id')
                    ->where('credit_ledger.reference_type', '=', LeadPurchase::class)
                    ->where('credit_ledger.type', '=', CreditLedgerType::REFUND->value);
            })
            ->groupBy('lead_purchases.id')
            ->selectRaw('lead_purchases.id as purchase_id')
            ->selectRaw('lead_purchases.price_cents as price_cents')
            ->get();

        $refunds = [];

        foreach ($rows as $row) {
            $refunds[(int) $row->purchase_id] = (int) $row->price_cents;
        }

        return $refunds;
    }

    /**
     * Alle Kaeufe auf Leads dieses Betreibers.
     *
     * @return Builder
     */
    private function purchaseQuery(Tenant $tenant, ?Carbon $from, ?Carbon $until)
    {
        $query = DB::table('lead_purchases')
            ->join('leads', 'leads.id', '=', 'lead_purchases.lead_id')
            ->leftJoin('funnels', 'funnels.id', '=', 'leads.funnel_id')
            ->join('tenants as buyers', 'buyers.id', '=', 'lead_purchases.buyer_tenant_id')
            ->where('leads.tenant_id', $tenant->getKey());

        if ($from !== null) {
            $query->where('lead_purchases.purchased_at', '>=', $from);
        }

        if ($until !== null) {
            $query->where('lead_purchases.purchased_at', '<=', $until);
        }

        return $query;
    }
}
