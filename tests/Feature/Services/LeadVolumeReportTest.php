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

    /** Welches Portal hat wie viele gebracht -- absteigend, das staerkste zuerst. */
    public function test_portale_werden_nach_menge_gezaehlt(): void
    {
        $this->leadAt('2026-10-02 10:00:00', portal: 'https://elektrikerportal.com/anfrage?utm=x');
        $this->leadAt('2026-10-03 10:00:00', portal: 'https://elektrikerportal.com');
        $this->leadAt('2026-10-04 10:00:00', portal: 'https://pfotencheck.tierarztportal.com');
        $this->leadAt('2026-10-05 10:00:00', portal: null);

        $counts = app(LeadVolumeReport::class)->perPortal(
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
        );

        $this->assertSame([
            'elektrikerportal.com' => 2,
            'pfotencheck.tierarztportal.com' => 1,
            'Ohne Portalangabe' => 1,
        ], $counts);
    }

    /**
     * Pfad, Abfrage und Schema gehoeren nicht zum Portal, die Unterdomain
     * schon: `pfotencheck.tierarztportal.com` ist ein eigener Auftritt und
     * keine Variante von `tierarztportal.com`.
     */
    public function test_portal_ist_der_hostname(): void
    {
        $report = app(LeadVolumeReport::class);

        $this->assertSame('elektrikerportal.com', $report->portalOf('https://elektrikerportal.com/anfrage?a=1'));
        $this->assertSame('elektrikerportal.com', $report->portalOf('elektrikerportal.com'));
        $this->assertSame('pfotencheck.tierarztportal.com', $report->portalOf('https://pfotencheck.tierarztportal.com'));
        $this->assertSame('Ohne Portalangabe', $report->portalOf(null));
        $this->assertSame('Ohne Portalangabe', $report->portalOf('   '));
    }

    /** Ein einzelnes Portal laesst sich aus der Reihe herausziehen. */
    public function test_reihe_laesst_sich_auf_ein_portal_einschraenken(): void
    {
        $this->leadAt('2026-09-10 10:00:00', portal: 'https://elektrikerportal.com');
        $this->leadAt('2026-10-10 10:00:00', portal: 'https://elektrikerportal.com');
        $this->leadAt('2026-10-11 10:00:00', portal: 'https://pfotencheck.tierarztportal.com');

        $report = app(LeadVolumeReport::class);
        $from = Carbon::parse('2026-09-01');
        $until = Carbon::parse('2026-10-31');

        $this->assertSame(
            ['September 2026' => 1, 'Oktober 2026' => 1],
            $report->perPeriod('month', $from, $until, 'elektrikerportal.com'),
        );
        $this->assertSame(
            ['September 2026' => 0, 'Oktober 2026' => 1],
            $report->perPeriod('month', $from, $until, 'pfotencheck.tierarztportal.com'),
        );
        $this->assertSame(1, $report->total($from, $until, 'pfotencheck.tierarztportal.com'));
        $this->assertSame(3, $report->total($from, $until));
    }

    /**
     * Die gestapelte Reihe: je Portal eine Zeile, alle gleich lang und in
     * derselben Reihenfolge wie die Beschriftungen -- sonst stapelt das
     * Diagramm Zahlen auf den falschen Monat.
     */
    public function test_gestapelte_reihen_passen_zu_den_beschriftungen(): void
    {
        $this->leadAt('2026-09-10 10:00:00', portal: 'https://elektrikerportal.com');
        $this->leadAt('2026-10-10 10:00:00', portal: 'https://elektrikerportal.com');
        $this->leadAt('2026-10-11 10:00:00', portal: 'https://elektrikerportal.com');
        $this->leadAt('2026-10-12 10:00:00', portal: 'https://pfotencheck.tierarztportal.com');

        $split = app(LeadVolumeReport::class)->perPortalAndPeriod(
            'month',
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-10-31'),
        );

        $this->assertSame(['September 2026', 'Oktober 2026'], $split['labels']);
        $this->assertSame([
            'elektrikerportal.com' => [1, 2],
            'pfotencheck.tierarztportal.com' => [0, 1],
        ], $split['series']);

        // Die Stapelsumme je Monat muss die Gesamtreihe ergeben, sonst zeigen
        // zwei Ansichten derselben Zahl verschiedene Balken.
        $this->assertSame(
            array_values(app(LeadVolumeReport::class)->perPeriod(
                'month',
                Carbon::parse('2026-09-01'),
                Carbon::parse('2026-10-31'),
            )),
            [
                $split['series']['elektrikerportal.com'][0] + $split['series']['pfotencheck.tierarztportal.com'][0],
                $split['series']['elektrikerportal.com'][1] + $split['series']['pfotencheck.tierarztportal.com'][1],
            ],
        );
    }

    /**
     * Ueber der Lesbarkeitsgrenze werden die kleinen Portale zu "Andere
     * Portale" addiert. Keine Zahl darf dabei verschwinden -- die Summe aller
     * Reihen bleibt die Gesamtzahl.
     */
    public function test_kleine_portale_werden_zu_anderen_zusammengefasst(): void
    {
        $portale = LeadVolumeReport::MAX_STACKED_PORTALS + 3;

        for ($i = 1; $i <= $portale; $i++) {
            // Das erste Portal bekommt zwei Leads, damit die Rangfolge
            // eindeutig ist und nicht von der Einfuegereihenfolge abhaengt.
            $leads = $i === 1 ? 2 : 1;

            for ($n = 0; $n < $leads; $n++) {
                $this->leadAt('2026-10-0'.(($n % 9) + 1).' 10:00:00', portal: 'https://portal'.$i.'.test');
            }
        }

        $split = app(LeadVolumeReport::class)->perPortalAndPeriod(
            'month',
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
        );

        $this->assertCount(LeadVolumeReport::MAX_STACKED_PORTALS + 1, $split['series']);
        $this->assertArrayHasKey('Andere Portale', $split['series']);
        $this->assertSame(
            $portale + 1,
            array_sum(array_map(static fn (array $row): int => array_sum($row), $split['series'])),
        );
    }

    private function leadAt(string $createdAt, ?Tenant $tenant = null, ?string $portal = null): Lead
    {
        $lead = Lead::factory()->create([
            'tenant_id' => ($tenant ?? Tenant::factory()->create())->getKey(),
            'embed_origin' => $portal,
        ]);

        // Das Eingangsdatum gehoert nicht zur Factory, sondern zum Fall:
        // `created_at` setzt Eloquent beim Anlegen selbst.
        $lead->forceFill(['created_at' => Carbon::parse($createdAt)])->save();

        return $lead;
    }
}
