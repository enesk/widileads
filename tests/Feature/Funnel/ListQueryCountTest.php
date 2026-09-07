<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\LeadState;
use App\Constants\TenantType;
use App\Marketplace\MarketplaceListing;
use App\Models\BuyerProfile;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\Feature\FeatureTest;

/**
 * Der Marktplatz kostet unabhaengig von seiner Laenge gleich viele Abfragen
 * (FB-041).
 *
 * MarketplaceListing laedt heute vor (`->with(['answers', 'funnel'])`), und es
 * muss das auch: LeadMatcher liest zu jedem Kandidaten die Antworten. Ohne das
 * Vorladen kostet eine Seite eine Abfrage je Lead -- still, und ausgerechnet an
 * der Stelle mit der groessten Kandidatenmenge.
 *
 * Bewusst keine feste Zahl erwartet, sondern der Vergleich zweier Laengen. Eine
 * feste Zahl waere bei jeder zusaetzlichen Bedingung falsch, ohne dass etwas
 * kaputt waere.
 *
 * Die Lead-Liste des Betreibers hat ihre eigene Zaehlung bereits in
 * LeadListTest (FB-034) -- die rendert die Livewire-Komponente und ist damit
 * naeher an der Wirklichkeit, als eine zweite Zaehlung hier es waere.
 */
class ListQueryCountTest extends FeatureTest
{
    public function test_der_marktplatz_kostet_unabhaengig_von_der_zeilenzahl_gleich_viele_abfragen(): void
    {
        [$tenant, $funnel] = $this->operatorWithFunnel();

        $buyer = $this->createTenant();
        $buyer->update(['type' => TenantType::BUYER]);

        $profile = BuyerProfile::query()->create(['tenant_id' => $buyer->getKey()]);

        $this->createLeads($tenant, $funnel, 2, LeadState::VERFUEGBAR);
        $twoRows = $this->queryCount(fn () => $this->renderMarketplace($buyer, $profile));

        $this->createLeads($tenant, $funnel, 6, LeadState::VERFUEGBAR);
        $eightRows = $this->queryCount(fn () => $this->renderMarketplace($buyer, $profile));

        $this->assertSame(
            $twoRows,
            $eightRows,
            'Der Marktplatz laedt je Zeile nach. Fehlt ein ->with() in App\Marketplace\MarketplaceListing?'
        );
    }

    /**
     * Greift dieselben Beziehungen ab, die der Marktplatz anzeigt -- ohne sie
     * waere die Zaehlung wertlos, weil das Nachladen erst beim Zugriff
     * passiert.
     */
    private function renderMarketplace(Tenant $buyer, BuyerProfile $profile): void
    {
        foreach (app(MarketplaceListing::class)->for($buyer, $profile) as $lead) {
            $lead->answers->count();
            $lead->funnel?->name;
        }
    }

    /**
     * @return array{0: Tenant, 1: Funnel}
     */
    private function operatorWithFunnel(): array
    {
        $tenant = $this->createTenant();
        $tenant->update(['type' => TenantType::OPERATOR]);

        return [$tenant, Funnel::factory()->create(['tenant_id' => $tenant->getKey()])];
    }

    private function createLeads(Tenant $tenant, Funnel $funnel, int $count, LeadState $state): void
    {
        for ($index = 0; $index < $count; $index++) {
            $lead = Lead::factory()->inState($state)->create([
                'tenant_id' => $tenant->getKey(),
                'funnel_id' => $funnel->getKey(),
            ]);

            LeadAnswer::query()->create([
                'lead_id' => $lead->getKey(),
                'field_key' => 'tierart',
                'value' => 'hund',
            ]);
        }
    }

    private function queryCount(callable $run): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $run();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }
}
