<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Page;

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Widgets\LeadsReceivedChart;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\Feature\FeatureTest;

/**
 * Das Balkendiagramm der eingegangenen Leads im Admin-Panel.
 *
 * Geprueft wird die Verbindung zum Seitenfilter: Ein Widget ist eine eigene
 * Livewire-Komponente, und ob es den Zeitraum der Dashboard-Seite tatsaechlich
 * bekommt, sieht man an den Zahlen -- nicht am Code.
 */
class LeadsReceivedChartTest extends FeatureTest
{
    public function test_diagramm_zeigt_die_leads_des_gefilterten_zeitraums(): void
    {
        $this->actingAs($this->createAdminUser());

        $this->leadAt('2026-09-10 10:00:00');
        $this->leadAt('2026-10-10 10:00:00');
        $this->leadAt('2026-10-11 10:00:00');

        $data = $this->chartData([
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'period' => 'month',
        ]);

        $this->assertSame(['Oktober 2026'], $data['labels']);
        $this->assertSame([2], $data['datasets'][0]['data']);
    }

    /**
     * Ohne Filterwerte zeigt das Diagramm das letzte Jahr nach Monaten --
     * dasselbe wie die Vorgabe des Dashboard-Filters. Eine leere Seite waere
     * die schlechtere Antwort auf einen fehlenden Wert.
     */
    public function test_ohne_filter_gilt_das_letzte_jahr(): void
    {
        $this->actingAs($this->createAdminUser());

        $this->leadAt(now()->subMonth()->toDateTimeString());

        $data = $this->chartData();

        $this->assertSame(1, array_sum($data['datasets'][0]['data']));
        $this->assertCount(13, $data['labels'], 'Das letzte Jahr nach Monaten sind 13 angeschnittene Monate.');
    }

    /**
     * Das Diagramm haengt auf dem Dashboard und wird dort auch gezeigt. Zwei
     * Widgets stehen auf einer Ausschlussliste in Dashboard::getWidgets(),
     * weil sie ihren eigenen Zeitraum mitbringen -- dieses nicht, es liest den
     * der Seite.
     */
    public function test_diagramm_haengt_auf_dem_dashboard(): void
    {
        $this->actingAs($this->createAdminUser());

        $this->assertContains(LeadsReceivedChart::class, app(Dashboard::class)->getWidgets());

        // Nur der Seitenaufbau: Filament-Widgets rendern lazy, die
        // Ueberschrift steht erst in der Livewire-Nachladung -- die prueft der
        // Test oben ueber getData().
        $this->get(Dashboard::getUrl([], true, 'admin'))->assertSuccessful();
    }

    /**
     * Ohne Auswahl zeigt das Diagramm je Portal eine eigene Reihe -- die
     * Frage "welches Portal bringt wie viele" ist damit ohne Klick
     * beantwortet.
     */
    public function test_ohne_auswahl_steht_jedes_portal_als_eigene_reihe(): void
    {
        $this->actingAs($this->createAdminUser());

        $this->leadAt('2026-10-10 10:00:00', 'https://elektrikerportal.com');
        $this->leadAt('2026-10-11 10:00:00', 'https://elektrikerportal.com');
        $this->leadAt('2026-10-12 10:00:00', 'https://pfotencheck.tierarztportal.com');

        $data = $this->chartData([
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'period' => 'month',
        ]);

        $this->assertSame(
            ['elektrikerportal.com', 'pfotencheck.tierarztportal.com'],
            array_column($data['datasets'], 'label'),
        );
        $this->assertSame([2], $data['datasets'][0]['data']);
        $this->assertSame([1], $data['datasets'][1]['data']);
    }

    /** Ein gewaehltes Portal schraenkt das Diagramm auf dessen Leads ein. */
    public function test_auswahl_schraenkt_auf_ein_portal_ein(): void
    {
        $this->actingAs($this->createAdminUser());

        $this->leadAt('2026-10-10 10:00:00', 'https://elektrikerportal.com');
        $this->leadAt('2026-10-12 10:00:00', 'https://pfotencheck.tierarztportal.com');

        $data = $this->chartData([
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'period' => 'month',
        ], portal: 'pfotencheck.tierarztportal.com');

        $this->assertSame(['pfotencheck.tierarztportal.com'], array_column($data['datasets'], 'label'));
        $this->assertSame([1], $data['datasets'][0]['data']);
    }

    /**
     * Die Auswahlliste nennt die Portale mit ihrer Menge, das staerkste
     * zuerst, und davor "Alle Portale".
     */
    public function test_auswahlliste_nennt_die_portale_mit_ihrer_menge(): void
    {
        $this->actingAs($this->createAdminUser());

        $this->leadAt('2026-10-10 10:00:00', 'https://elektrikerportal.com');
        $this->leadAt('2026-10-11 10:00:00', 'https://elektrikerportal.com');
        $this->leadAt('2026-10-12 10:00:00', 'https://pfotencheck.tierarztportal.com');

        $widget = $this->widget([
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'period' => 'month',
        ]);

        $filters = (new ReflectionMethod($widget, 'getFilters'))->invoke($widget);

        $this->assertSame([
            'all' => 'Alle Portale',
            'elektrikerportal.com' => 'elektrikerportal.com (2)',
            'pfotencheck.tierarztportal.com' => 'pfotencheck.tierarztportal.com (1)',
        ], $filters);
    }

    /**
     * Die Daten, die das Diagramm zeichnet.
     *
     * Ueber die Komponente und nicht ueber den Report: Gefragt ist, ob der
     * Seitenfilter ankommt. `getData()` ist in Filament protected -- ein
     * oeffentlicher Zugang nur fuer den Test waere eine Tuer in der
     * Komponente, die im Betrieb niemand braucht.
     *
     * @param  array<string, string>  $pageFilters
     * @return array<string, mixed>
     */
    private function chartData(array $pageFilters = [], ?string $portal = null): array
    {
        $widget = $this->widget($pageFilters, $portal);

        $data = new ReflectionMethod($widget, 'getData');

        /** @var array<string, mixed> $result */
        $result = $data->invoke($widget);

        return $result;
    }

    /**
     * Das Widget mit gesetztem Seitenfilter und gesetzter Portalauswahl.
     *
     * @param  array<string, string>  $pageFilters
     */
    private function widget(array $pageFilters = [], ?string $portal = null): LeadsReceivedChart
    {
        $component = Livewire::test(LeadsReceivedChart::class, ['pageFilters' => $pageFilters]);

        if ($portal !== null) {
            $component->set('filter', $portal);
        }

        /** @var LeadsReceivedChart $widget */
        $widget = $component->instance();

        return $widget;
    }

    private function leadAt(string $createdAt, ?string $portal = null): void
    {
        Lead::factory()
            ->create([
                'tenant_id' => Tenant::factory()->create()->getKey(),
                'embed_origin' => $portal,
            ])
            ->forceFill(['created_at' => Carbon::parse($createdAt)])
            ->save();
    }
}
