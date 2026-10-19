<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Lead;
use App\Models\Tenant;
use App\Services\LeadVolumeReport;
use Illuminate\Support\Carbon;
use Tests\Feature\FeatureTest;

/**
 * Eingegangene Leads je Zeitraum -- die Zahlen hinter dem Diagramm im
 * Admin-Panel.
 *
 * Geprueft wird, was an einer Auswertung still falsch sein kann: die
 * Zuordnung zum richtigen Topf (ein Lead vom Monatsersten darf nicht in den
 * Vormonat fallen), die leeren Zeitraeume, die Grenzen des Zeitraums und die
 * Mandanten -- die Auswertung des Betreibers muss ueber alle Workspaces
 * zaehlen, sonst zeigt sie je nach gesetztem Kontext etwas anderes.
 */
class LeadVolumeReportTest extends FeatureTest
{
    public function test_leads_landen_im_monat_ihres_eingangs(): void
    {
        $this->leadAt('2026-08-31 23:59:00');
        $this->leadAt('2026-09-01 00:00:00');
        $this->leadAt('2026-09-15 12:00:00');
        $this->leadAt('2026-10-01 00:00:00');

        $counts = app(LeadVolumeReport::class)->perPeriod(
            'month',
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-10-31'),
        );

        $this->assertSame([
            'August 2026' => 1,
            'September 2026' => 2,
            'Oktober 2026' => 1,
        ], $counts);
    }

    /**
     * Ein Monat ohne Leads ist die wichtigste Zahl der Reihe. Faellt er aus
     * der Liste, sieht das Diagramm aus, als haette es diesen Monat nicht
     * gegeben.
     */
    public function test_leerer_monat_steht_mit_null_darin(): void
    {
        $this->leadAt('2026-08-10 09:00:00');
        $this->leadAt('2026-10-10 09:00:00');

        $counts = app(LeadVolumeReport::class)->perPeriod(
            'month',
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-10-31'),
        );

        $this->assertSame(['August 2026' => 1, 'September 2026' => 0, 'Oktober 2026' => 1], $counts);
    }

    /** Ausserhalb des Zeitraums wird nicht gezaehlt, an seinen Raendern schon. */
    public function test_zeitraum_schliesst_seine_raender_ein_und_sonst_nichts(): void
    {
        $this->leadAt('2026-09-30 23:59:59');
        $this->leadAt('2026-10-01 00:00:01');
        $this->leadAt('2026-10-31 23:59:59');
        $this->leadAt('2026-11-01 00:00:01');

        $report = app(LeadVolumeReport::class);
        $from = Carbon::parse('2026-10-01');
        $until = Carbon::parse('2026-10-31');

        $this->assertSame(['Oktober 2026' => 2], $report->perPeriod('month', $from, $until));
        $this->assertSame(2, $report->total($from, $until));
    }

    /**
     * Die Auswertung des Betreibers zaehlt ueber alle Workspaces. Zaehlte sie
     * nur den gesetzten Mandanten, zeigte dasselbe Diagramm je nach Vorgeschichte
     * der Sitzung eine andere Zahl.
     */
    public function test_gezaehlt_wird_ueber_alle_workspaces(): void
    {
        $this->leadAt('2026-10-05 10:00:00', Tenant::factory()->create());
        $this->leadAt('2026-10-06 10:00:00', Tenant::factory()->create());

        $counts = app(LeadVolumeReport::class)->perPeriod(
            'month',
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
        );

        $this->assertSame(['Oktober 2026' => 2], $counts);
    }

    /** Tage, Wochen und Jahre tragen eine eindeutige Beschriftung je Topf. */
    public function test_andere_gruppierungen_fassen_dieselben_leads_zusammen(): void
    {
        $this->leadAt('2026-10-05 08:00:00');
        $this->leadAt('2026-10-05 20:00:00');
        $this->leadAt('2026-10-08 08:00:00');

        $report = app(LeadVolumeReport::class);
        $from = Carbon::parse('2026-10-05');
        $until = Carbon::parse('2026-10-08');

        $this->assertSame([
            '05.10.2026' => 2,
            '06.10.2026' => 0,
            '07.10.2026' => 0,
            '08.10.2026' => 1,
        ], $report->perPeriod('day', $from, $until));

        $this->assertSame(['KW 41/2026' => 3], $report->perPeriod('week', $from, $until));
        $this->assertSame(['2026' => 3], $report->perPeriod('year', $from, $until));
    }

    /** Eine unbekannte Gruppierung faellt auf Monate zurueck, statt zu brechen. */
    public function test_unbekannte_gruppierung_wird_zum_monat(): void
    {
        $this->leadAt('2026-10-05 08:00:00');

        $counts = app(LeadVolumeReport::class)->perPeriod(
            'quartal',
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
        );

        $this->assertSame(['Oktober 2026' => 1], $counts);
    }

    private function leadAt(string $createdAt, ?Tenant $tenant = null): Lead
    {
        $lead = Lead::factory()->create([
            'tenant_id' => ($tenant ?? Tenant::factory()->create())->getKey(),
        ]);

        // Das Eingangsdatum gehoert nicht zur Factory, sondern zum Fall:
        // `created_at` setzt Eloquent beim Anlegen selbst.
        $lead->forceFill(['created_at' => Carbon::parse($createdAt)])->save();

        return $lead;
    }
}
