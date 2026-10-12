<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\Onboarding\OnboardingFunnelQuery;
use Filament\Widgets\Widget;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * LP-ONBOARD-015: Der Onboarding-Trichter als horizontale Balken.
 *
 * Neun Stufen von der Registrierung bis zum ersten Anruf, je Stufe die
 * absolute Zahl und die Quote gegen die Vorstufe -- die Stelle mit der
 * kleinsten Quote ist die Stelle, an der gearbeitet werden muss. Darunter die
 * beiden Kennzahlen, die das Ganze zusammenfassen: Aktivierung in sieben Tagen
 * und der Median bis zum ersten Kauf.
 *
 * Der Zeitraum-Filter liegt bewusst in diesem Widget und nicht auf der Seite:
 * Ein Filament-Widget ist eine eigene Livewire-Komponente, und ein Wert von
 * der Hostseite erreicht es nur beim Einhaengen. Der Balken wuerde beim
 * Umschalten stehen bleiben. Aus demselben Grund steht der CSV-Export hier --
 * er muss denselben Zeitraum erwischen, den man gerade ansieht.
 *
 * Reine Ansicht: Von hier aus laesst sich kein Haken setzen oder entfernen,
 * das bleibt beim OnboardingService.
 */
class OnboardingFunnelChart extends Widget
{
    protected string $view = 'filament.admin.widgets.onboarding-funnel-chart';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    /** Zeitraum in Tagen, nach Registrierungsdatum. */
    public int $days = 7;

    /**
     * Haelt den Zeitraum auf einem der erlaubten Werte -- ein manipulierter
     * Livewire-Aufruf soll keine Abfrage ueber alle Jahre ausloesen.
     */
    public function updatedDays(): void
    {
        $this->days = app(OnboardingFunnelQuery::class)->normalizePeriod($this->days);
    }

    /**
     * @return array<string, mixed>
     */
    public function funnel(): array
    {
        return app(OnboardingFunnelQuery::class)->funnel($this->days);
    }

    /**
     * @return array<int, int>
     */
    public function periods(): array
    {
        return OnboardingFunnelQuery::PERIODS;
    }

    public function activationWindowDays(): int
    {
        return OnboardingFunnelQuery::ACTIVATION_WINDOW_DAYS;
    }

    /**
     * Beschriftung einer Stufe.
     */
    public function stageLabel(string $stage): string
    {
        return __('reports.onboarding.stages.'.$stage);
    }

    /**
     * Eine Dauer in Stunden menschenlesbar -- unter 48 Stunden in Stunden,
     * darueber in Tagen. "73,4 Stunden" liest niemand.
     */
    public function formatHours(?float $hours): string
    {
        if ($hours === null) {
            return __('reports.onboarding.no_data');
        }

        if ($hours < 48) {
            return trans_choice('reports.onboarding.hours', (int) round($hours), [
                'count' => number_format($hours, 1, ',', '.'),
            ]);
        }

        $days = $hours / 24;

        return trans_choice('reports.onboarding.days', (int) round($days), [
            'count' => number_format($days, 1, ',', '.'),
        ]);
    }

    /**
     * Der Trichter als CSV -- eine Zeile je Stufe, danach die beiden
     * Kennzahlen.
     *
     * Bewusst ein Direktdownload wie in LeadReachability (FB-085) und kein
     * Hintergrund-Export: Es sind elf Zeilen, und die Zahlen liegen ohnehin
     * schon im Cache.
     */
    public function exportCsv(): StreamedResponse
    {
        $funnel = $this->funnel();

        return response()->streamDownload(function () use ($funnel): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            // Ohne BOM oeffnet Excel die Umlaute falsch.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                __('reports.onboarding.csv.stage'),
                __('reports.onboarding.csv.count'),
                __('reports.onboarding.csv.percent_of_previous'),
                __('reports.onboarding.csv.percent_of_total'),
            ], ';', '"', '');

            foreach ($funnel['stages'] as $stage) {
                fputcsv($handle, [
                    $this->stageLabel($stage['key']),
                    $stage['count'],
                    number_format($stage['percent_of_previous'], 1, ',', ''),
                    number_format($stage['percent_of_total'], 1, ',', ''),
                ], ';', '"', '');
            }

            fputcsv($handle, [], ';', '"', '');
            fputcsv($handle, [
                __('reports.onboarding.activation_rate'),
                number_format($funnel['activation_rate'], 1, ',', ''),
            ], ';', '"', '');
            fputcsv($handle, [
                __('reports.onboarding.median'),
                $funnel['median_hours_to_first_purchase'] === null
                    ? ''
                    : number_format($funnel['median_hours_to_first_purchase'], 1, ',', ''),
            ], ';', '"', '');

            fclose($handle);
        }, sprintf('onboarding-trichter-%d-tage.csv', $funnel['days']), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
