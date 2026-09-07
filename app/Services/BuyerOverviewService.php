<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\ComplaintStatus;
use App\Constants\TenantType;
use App\Models\LeadComplaint;
use App\Models\LeadPurchase;
use App\Models\Tenant;

/**
 * Die Sicht des Operators auf seine Kaeufer (FB-060).
 *
 * Beantwortet drei Fragen je Kaeufer: Wie viel hat er gekauft, wie oft hat er
 * reklamiert, und faellt er damit aus dem Rahmen.
 *
 * **Auffaellig heisst nicht schuldig.** Eine hohe Reklamationsquote kann
 * bedeuten, dass ein Kaeufer schlechte Leads bekommen hat -- oder dass er zu
 * grosszuegig reklamiert. Diese Uebersicht sagt nur, wo hinzusehen sich lohnt;
 * die Entscheidung trifft ein Mensch.
 *
 * Gemessen wird der Abstand zum Durchschnitt in Prozentpunkten, nicht als
 * Vielfaches: Bei einem Durchschnitt nahe null waere jeder Faktor sinnlos
 * gross, waehrend der Abstand aussagekraeftig bleibt.
 */
class BuyerOverviewService
{
    public function __construct(private readonly LeadComplaintService $complaints) {}

    /**
     * Alle Kaeufer mit ihren Kennzahlen, auffaellige zuerst.
     *
     * @return list<array{
     *     tenant: Tenant,
     *     purchases: int,
     *     revenue_cents: int,
     *     approved_complaints: int,
     *     complaint_rate: float,
     *     deviation_points: float,
     *     flagged: bool
     * }>
     */
    public function all(): array
    {
        $buyers = Tenant::query()
            ->where('type', TenantType::BUYER->value)
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($buyers as $buyer) {
            $purchases = LeadPurchase::query()->where('buyer_tenant_id', $buyer->getKey())->count();

            $rows[] = [
                'tenant' => $buyer,
                'purchases' => $purchases,
                'revenue_cents' => (int) LeadPurchase::query()
                    ->where('buyer_tenant_id', $buyer->getKey())
                    ->sum('price_cents'),
                'approved_complaints' => LeadComplaint::query()
                    ->where('buyer_tenant_id', $buyer->getKey())
                    ->where('status', ComplaintStatus::APPROVED->value)
                    ->count(),
                'complaint_rate' => $this->complaints->approvedComplaintRateFor($buyer),
                'deviation_points' => 0,
                'flagged' => false,
            ];
        }

        return $this->flagOutliers($rows);
    }

    /**
     * Der Durchschnitt und die Abweichung davon.
     *
     * Der Durchschnitt wird ueber alle Kaeufe gebildet, nicht ueber alle
     * Kaeufer: Sonst zoege ein Kaeufer mit drei Kaeufen den Schnitt genauso
     * stark wie einer mit dreihundert.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function flagOutliers(array $rows): array
    {
        $totalPurchases = array_sum(array_column($rows, 'purchases'));
        $totalComplaints = array_sum(array_column($rows, 'approved_complaints'));

        $average = $totalPurchases === 0 ? 0 : $totalComplaints / $totalPurchases;

        $threshold = (int) config('funnel.marketplace.buyer_review.complaint_rate_flag_points');
        $minimum = (int) config('funnel.marketplace.buyer_review.minimum_purchases');

        foreach ($rows as $index => $row) {
            $deviation = ($row['complaint_rate'] - $average) * 100;

            $rows[$index]['deviation_points'] = $deviation;

            // Zu wenige Kaeufe ergeben keine Quote, sondern Rauschen.
            $rows[$index]['flagged'] = $row['purchases'] >= $minimum && $deviation > $threshold;
        }

        // Auffaellige zuerst, danach die groessten Kaeufer -- so steht oben,
        // was Aufmerksamkeit braucht.
        usort($rows, static function (array $a, array $b): int {
            return [$b['flagged'], $b['deviation_points']] <=> [$a['flagged'], $a['deviation_points']];
        });

        return $rows;
    }

    /**
     * Der Durchschnitt ueber alle Kaeufer, fuer die Anzeige.
     */
    public function averageComplaintRate(): float
    {
        $purchases = LeadPurchase::query()->count();

        $complaints = LeadComplaint::query()
            ->where('status', ComplaintStatus::APPROVED->value)
            ->count();

        return $purchases === 0 ? 0 : $complaints / $purchases;
    }
}
