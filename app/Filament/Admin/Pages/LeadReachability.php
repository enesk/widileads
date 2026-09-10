<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Tenant;
use App\Services\LeadReachabilityService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FB-085: Erreichbarkeitsquote je Kaeufer.
 *
 * Die Gegenprobe zur Gutschrift: Wer sehr viele Leads als nicht erreichbar
 * zurueckgibt, faellt hier auf. Bewusst eine reine Ansicht -- weder Versuche
 * noch `contact_status` lassen sich von hier aus aendern; entschieden wird
 * ausschliesslich im LeadResolver (FB-083).
 */
class LeadReachability extends Page
{
    protected string $view = 'filament.admin.pages.lead-reachability';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoneArrowUpRight;

    protected static ?int $navigationSort = 16;

    /** Zeitraum ueber `leads.resolved_at`, leer heisst: ohne Einschraenkung. */
    public string $from = '';

    public string $to = '';

    public function getHeading(): string|Htmlable
    {
        return __('call.reachability.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('call.reachability.heading');
    }

    public static function getNavigationLabel(): string
    {
        return __('call.reachability.nav_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.leads');
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return app(LeadReachabilityService::class)->summary($this->fromDate(), $this->toDate());
    }

    public function flagThreshold(): int
    {
        return (int) config('lead_calls.unreachable_rate_flag_points');
    }

    /**
     * Die Uebersicht als CSV -- eine Zeile je Kaeufer, dazu die Gesamtzeile.
     */
    public function exportCsv(): StreamedResponse
    {
        $summary = $this->summary();

        return response()->streamDownload(function () use ($summary): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            // Ohne BOM oeffnet Excel die Umlaute falsch.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                __('call.reachability.csv.buyer'),
                __('call.reachability.csv.leads_total'),
                __('call.reachability.csv.billable'),
                __('call.reachability.csv.unreachable'),
                __('call.reachability.csv.open'),
                __('call.reachability.csv.rate'),
                __('call.reachability.csv.deviation'),
                __('call.reachability.csv.flagged'),
            ], ';');

            foreach ($summary['rows'] as $row) {
                $tenant = $row['tenant'];

                fputcsv($handle, [
                    $tenant instanceof Tenant ? $tenant->name : (string) $row['buyer_tenant_id'],
                    $row['leads_total'],
                    $row['billable'],
                    $row['unreachable'],
                    $row['open'],
                    number_format($row['unreachable_rate'] * 100, 1, ',', ''),
                    number_format($row['deviation_points'], 1, ',', ''),
                    $row['flagged'] ? __('call.reachability.flagged') : '',
                ], ';');
            }

            fputcsv($handle, [], ';');
            fputcsv($handle, [
                __('call.reachability.csv.average'),
                $summary['leads_total'],
                $summary['billable'],
                $summary['unreachable'],
                '',
                number_format($summary['average_rate'] * 100, 1, ',', ''),
            ], ';');

            fclose($handle);
        }, sprintf(
            'erreichbarkeit-%s-%s.csv',
            $this->from !== '' ? $this->from : 'anfang',
            $this->to !== '' ? $this->to : 'heute',
        ), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Der Zeitraum ist tagesgenau und schliesst beide Randtage ein -- wer
     * "bis 31.10." waehlt, meint den ganzen 31. Oktober.
     */
    private function fromDate(): ?Carbon
    {
        return $this->from === '' ? null : Carbon::parse($this->from)->startOfDay();
    }

    private function toDate(): ?Carbon
    {
        return $this->to === '' ? null : Carbon::parse($this->to)->endOfDay();
    }
}
