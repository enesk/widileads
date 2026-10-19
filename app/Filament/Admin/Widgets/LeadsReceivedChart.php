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
 *
 * **Die Portalauswahl ist der eigene Filter des Widgets** und liegt bewusst
 * nicht im Seitenfilter: Ein Portal ist nichts, was die Geld-Diagramme
 * daneben kennen. Ohne Auswahl zeigt das Diagramm alle Portale gestapelt --
 * dann ist die Frage "welches Portal bringt wie viele" ohne Klick
 * beantwortet, und die Gesamthoehe der Balken bleibt dieselbe wie bei einer
 * Auswahl ueber alles.
 */
class LeadsReceivedChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 6;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /** Der Wert der Portalauswahl, der fuer "nicht eingeschraenkt" steht. */
    private const ALL_PORTALS = 'all';

    /**
     * Vorbelegung der Auswahl. Ohne sie stuende im Feld der erste Eintrag,
     * waehrend das Diagramm alle Portale zeigt -- Feld und Bild sagten
     * Verschiedenes.
     */
    public ?string $filter = self::ALL_PORTALS;

    /**
     * Die Farben der gestapelten Reihen.
     *
     * Fest und nicht aus dem Filament-Thema: Die Reihen sind Portale und
     * keine Zustaende -- sie brauchen Unterscheidbarkeit, nicht Bedeutung.
     * Die Reihenfolge ist die des Rankings, damit dasselbe Portal zwischen
     * zwei Aufrufen dieselbe Farbe behaelt, solange sich die Rangfolge nicht
     * aendert.
     *
     * @var list<string>
     */
    private const COLOURS = [
        'rgba(59, 130, 246, 0.85)',
        'rgba(16, 185, 129, 0.85)',
        'rgba(245, 158, 11, 0.85)',
        'rgba(139, 92, 246, 0.85)',
        'rgba(236, 72, 153, 0.85)',
        'rgba(20, 184, 166, 0.85)',
        'rgba(234, 88, 12, 0.85)',
        'rgba(100, 116, 139, 0.85)',
    ];

    public function getHeading(): string|Htmlable|null
    {
        return __('reports.leads.heading');
    }

    public function getDescription(): string|Htmlable|null
    {
        $portal = $this->portal();

        if ($portal === null) {
            return __('reports.leads.description', ['count' => $this->total()]);
        }

        return __('reports.leads.description_portal', [
            'portal' => $portal,
            'count' => $this->total(),
        ]);
    }

    /**
     * Die Portale als Auswahl, das staerkste zuerst, davor "Alle Portale".
     *
     * Die Liste kommt aus dem Zeitraum selbst: Ein Portal, das im gewaehlten
     * Zeitraum keinen einzigen Lead gebracht hat, waere eine Auswahl, die auf
     * ein leeres Diagramm fuehrt.
     *
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        $filters = [self::ALL_PORTALS => __('reports.leads.all_portals')];

        foreach ($this->report()->perPortal($this->startDate(), $this->endDate()) as $portal => $count) {
            $filters[$portal] = $portal.' ('.number_format($count, 0, ',', '.').')';
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $portal = $this->portal();

        if ($portal !== null) {
            $counts = $this->report()->perPeriod($this->period(), $this->startDate(), $this->endDate(), $portal);

            return [
                'datasets' => [[
                    'label' => $portal,
                    'data' => array_values($counts),
                    'backgroundColor' => self::COLOURS[0],
                ]],
                'labels' => array_keys($counts),
            ];
        }

        $split = $this->report()->perPortalAndPeriod($this->period(), $this->startDate(), $this->endDate());
        $datasets = [];
        $index = 0;

        foreach ($split['series'] as $name => $values) {
            $datasets[] = [
                'label' => $name,
                'data' => $values,
                'backgroundColor' => self::COLOURS[$index % count(self::COLOURS)],
            ];

            $index++;
        }

        return [
            'datasets' => $datasets,
            'labels' => $split['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Gestapelt, solange alle Portale gezeigt werden: Die Gesamthoehe eines
     * Monats muss dieselbe bleiben wie ohne Aufteilung, sonst zeigen zwei
     * Ansichten derselben Zahl verschiedene Balken. Die Legende steht nur
     * dann, wenn es etwas zu unterscheiden gibt.
     *
     * Ganze Zahlen auf der Achse: Leads sind Stueck, und "2,5 Leads" gibt es
     * nicht.
     *
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        $stacked = $this->portal() === null;

        return [
            'scales' => [
                'x' => ['stacked' => $stacked],
                'y' => [
                    'stacked' => $stacked,
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => $stacked, 'position' => 'bottom'],
            ],
        ];
    }

    private function total(): int
    {
        return $this->report()->total($this->startDate(), $this->endDate(), $this->portal());
    }

    /**
     * Das gewaehlte Portal, oder null fuer alle.
     *
     * Ein Portal, das es im Zeitraum nicht gibt, gilt als "alle": Der
     * Zeitraum laesst sich umstellen, nachdem ein Portal gewaehlt wurde, und
     * ein leeres Diagramm ohne Erklaerung waere die schlechtere Antwort.
     */
    private function portal(): ?string
    {
        $filter = $this->filter;

        if (! is_string($filter) || $filter === self::ALL_PORTALS || $filter === '') {
            return null;
        }

        $known = array_keys($this->report()->perPortal($this->startDate(), $this->endDate()));

        return in_array($filter, $known, strict: true) ? $filter : null;
    }

    private function report(): LeadVolumeReport
    {
        return app(LeadVolumeReport::class);
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
