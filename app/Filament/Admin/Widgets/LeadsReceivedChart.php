<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\LeadVolumeReport;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

/**
 * Eingegangene Leads je Zeitraum, als Balken.
 *
 * Balken und nicht Linie: Die Zahl eines Monats ist eine Summe und kein
 * Verlauf -- eine Linie zwischen zwei Monatssummen behauptet Zwischenwerte,
 * die es nicht gibt.
 *
 * Zeitraum und Gruppierung kommen aus dem Filter der Dashboard-Seite
 * (`InteractsWithPageFilters`), damit hier nicht ein zweiter Zeitraum neben
 * dem der Geld-Diagramme steht. Vorgabe ist deshalb auch deren Vorgabe: das
 * letzte Jahr, nach Monaten.
 *
 * Gezaehlt wird im LeadVolumeReport und nicht hier: Eine zweite Zaehlweise im
 * Widget waere die Stelle, an der das Diagramm eine andere Zahl zeigt als
 * jede andere Auswertung.
 */
class LeadsReceivedChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 6;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string|Htmlable|null
    {
        return __('reports.leads.heading');
    }

    public function getDescription(): string|Htmlable|null
    {
        return __('reports.leads.description', ['count' => $this->total()]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $counts = app(LeadVolumeReport::class)->perPeriod(
            $this->period(),
            $this->startDate(),
            $this->endDate(),
        );

        return [
            'datasets' => [
                [
                    'label' => __('reports.leads.dataset'),
                    'data' => array_values($counts),
                ],
            ],
            'labels' => array_keys($counts),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Ganze Zahlen auf der Achse: Leads sind Stueck, und "2,5 Leads" gibt es
     * nicht.
     *
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }

    private function total(): int
    {
        return app(LeadVolumeReport::class)->total($this->startDate(), $this->endDate());
    }

    private function period(): string
    {
        $period = $this->pageFilters['period'] ?? null;

        return is_string($period) ? $period : 'month';
    }

    private function startDate(): ?Carbon
    {
        return $this->filterDate('start_date');
    }

    private function endDate(): ?Carbon
    {
        return $this->filterDate('end_date');
    }

    /**
     * Ein Datum aus dem Seitenfilter. Fehlt es oder ist es unlesbar, gilt die
     * Vorgabe des Reports -- besser das letzte Jahr als eine leere Seite.
     */
    private function filterDate(string $key): ?Carbon
    {
        $value = $this->pageFilters[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
