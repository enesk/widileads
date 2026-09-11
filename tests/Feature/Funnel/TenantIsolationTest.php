<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadWatchlistEntry;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\Feature\FeatureTest;

/**
 * Kein Mandant sieht die Datensaetze eines anderen (FB-043).
 *
 * Der Schutz haengt an einer einzigen Stelle -- dem globalen Scope aus
 * BelongsToTenant. Faellt er weg oder vergisst ein neues Modell den Trait,
 * sieht ein Betreiber die Leads eines anderen, und niemand merkt es: Die
 * Abfrage laeuft, sie liefert nur zu viel.
 *
 * Die Pruefung sucht die betroffenen Modelle selbst zusammen, statt sie
 * aufzuzaehlen. Ein neues mandantengebundenes Modell ohne Eintrag in
 * `records()` macht diesen Test rot -- das ist die Absicht. Eine Liste, die
 * jemand pflegen muss, waere genau die Stelle, an der der naechste Fall
 * durchrutscht.
 */
class TenantIsolationTest extends FeatureTest
{
    public function test_jedes_mandantengebundene_modell_ist_in_dieser_pruefung_erfasst(): void
    {
        $missing = array_values(array_diff(
            $this->tenantScopedModels(),
            array_keys($this->records($this->createTenant())),
        ));

        $this->assertSame([], $missing, implode("\n", array_merge(
            ['Diese Modelle haengen am Mandanten, werden hier aber nicht geprueft:'],
            $missing,
            ['Ergaenze sie in TenantIsolationTest::records().'],
        )));
    }

    public function test_kein_mandant_sieht_die_datensaetze_eines_anderen(): void
    {
        $mine = $this->createTenant();
        $theirs = $this->createTenant();

        $ownRecords = $this->records($mine);
        $foreignRecords = $this->records($theirs);

        Filament::setTenant($mine, isQuiet: true);

        foreach ($ownRecords as $class => $own) {
            $foreign = $foreignRecords[$class];

            $visible = $class::query()->pluck('id')->all();

            $this->assertSame(
                [$own->getKey()],
                $visible,
                $class.': Die Abfrage liefert Datensaetze eines fremden Mandanten.'
            );

            $this->assertNull(
                $class::query()->find($foreign->getKey()),
                $class.': Ein fremder Datensatz ist ueber seine Kennung erreichbar.'
            );
        }
    }

    public function test_ein_neuer_datensatz_bekommt_den_mandanten_aus_dem_kontext(): void
    {
        $mine = $this->createTenant();

        Filament::setTenant($mine, isQuiet: true);

        $funnel = Funnel::query()->create([
            'name' => 'Ohne ausdrueckliche Mandantenangabe',
            'slug' => 'ohne-mandantenangabe',
            'public_token' => str_repeat('a', 26),
        ]);

        $this->assertSame($mine->getKey(), $funnel->tenant_id);
    }

    /**
     * Je mandantengebundenem Modell ein Datensatz dieses Mandanten.
     *
     * @return array<class-string<Model>, Model>
     */
    private function records(Tenant $tenant): array
    {
        $funnel = Funnel::factory()->create(['tenant_id' => $tenant->getKey()]);

        $lead = Lead::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'funnel_id' => $funnel->getKey(),
        ]);

        return [
            Funnel::class => $funnel,
            Lead::class => $lead,
            LeadWatchlistEntry::class => LeadWatchlistEntry::query()->create([
                'tenant_id' => $tenant->getKey(),
                'lead_id' => $lead->getKey(),
            ]),
        ];
    }

    /**
     * Alle Modelle unter app/Models, die den Mandanten-Scope benutzen.
     *
     * @return list<class-string<Model>>
     */
    private function tenantScopedModels(): array
    {
        $models = [];

        $finder = (new Finder)->files()->in(app_path('Models'))->depth(0)->name('*.php');

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $class = 'App\\Models\\'.$file->getBasename('.php');

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            if (in_array(BelongsToTenant::class, $this->traitsOf($reflection), true)) {
                $models[] = $class;
            }
        }

        sort($models);

        return $models;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @return list<class-string>
     */
    private function traitsOf(ReflectionClass $reflection): array
    {
        $traits = [];

        while ($reflection instanceof ReflectionClass) {
            $traits = array_merge($traits, array_keys($reflection->getTraits()));
            $reflection = $reflection->getParentClass();
        }

        return array_values(array_unique($traits));
    }
}
