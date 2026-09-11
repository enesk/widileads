<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\PurchaseStatus;
use App\Models\Tenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Umsatzuebersicht eines Betreibers (FB-072, auf das Wallet umgestellt mit
 * LP-WALLET-022).
 *
 * Was haben die eigenen Funnels eingebracht, und was ist davon wieder
 * abgegangen? Aufgeschluesselt je Funnel und je Kaeufer.
 *
 * **Gezeigt wird der Erloes, nicht der Kaufpreis.** Massgeblich ist
 * `seller_net_cents` -- der Betrag, der nach Abzug der Plattformprovision im
 * Verkaeufer-Wallet landet. Der Bruttopreis waere die groessere, aber falsche
 * Zahl: Sie stuende neben einem Wallet-Saldo, der sie nie erreicht.
 *
 * **Gutschriften kommen aus dem Kaufbeleg.** Eine anerkannte Reklamation setzt
 * den Kauf ueber PurchaseService::refund() auf `refunded` und bucht den Erloes
 * beim Verkaeufer zurueck. Der Stand des Belegs ist damit die Quelle -- das
 * Guthabenjournal wird nicht mehr gelesen.
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
        return [
            'by_funnel' => $this->group($tenant, $from, $until, 'funnels.name'),
            'by_buyer' => $this->group($tenant, $from, $until, 'buyers.name'),
            'totals' => $this->group($tenant, $from, $until, null),
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
     * @return list<array<string, mixed>>
     */
    private function group(Tenant $tenant, ?Carbon $from, ?Carbon $until, ?string $labelColumn): array
    {
        $refunded = 'lead_purchases.status = ?';

        $rows = $this->purchaseQuery($tenant, $from, $until)
            ->when(
                $labelColumn !== null,
                fn ($query) => $query->selectRaw($labelColumn.' as label')->groupBy($labelColumn),
            )
            ->selectRaw('lead_purchases.currency as currency')
            ->selectRaw('count(*) as sold')
            ->selectRaw('sum(lead_purchases.seller_net_cents) as revenue_cents')
            ->selectRaw('sum(case when '.$refunded.' then 1 else 0 end) as refunds', [PurchaseStatus::REFUNDED->value])
            ->selectRaw(
                'sum(case when '.$refunded.' then lead_purchases.seller_net_cents else 0 end) as refunded_cents',
                [PurchaseStatus::REFUNDED->value],
            )
            ->groupBy('lead_purchases.currency')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $revenue = (int) $row->revenue_cents;
            $refundedCents = (int) $row->refunded_cents;

            $entry = [
                'currency' => (string) $row->currency,
                'sold' => (int) $row->sold,
                'revenue_cents' => $revenue,
                'refunds' => (int) $row->refunds,
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
