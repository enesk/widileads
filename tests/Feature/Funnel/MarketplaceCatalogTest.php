<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\FunnelStatus;
use App\Marketplace\MarketplaceCatalog;
use App\Models\Funnel;
use App\Models\Tenant;
use Tests\Feature\FeatureTest;

/**
 * FB-051: Der Funnel-Katalog ist die einzige Stelle, an der der Mandanten-Scope
 * bewusst abgeschaltet wird.
 *
 * Ein Kaeufer soll seine Kaufkriterien auf einzelne Funnels einschraenken
 * koennen und muss dafuer wissen, welche es gibt. Freigegeben ist ausschliesslich
 * die Angebotsliste -- Kennung und Name veroeffentlichter Funnels von
 * Betreiber-Mandanten. Dieser Test haelt beide Grenzen fest: welche Funnels
 * herauskommen und welche Felder.
 */
class MarketplaceCatalogTest extends FeatureTest
{
    public function test_only_published_funnels_of_operator_tenants_are_listed(): void
    {
        $operator = Tenant::factory()->operator()->create();
        $otherOperator = Tenant::factory()->operator()->create();
        $buyer = Tenant::factory()->buyer()->create();

        $published = Funnel::factory()->create([
            'tenant_id' => $operator->getKey(),
            'name' => 'Pfotencheck',
            'status' => FunnelStatus::PUBLISHED,
        ]);

        // Auch der Funnel eines zweiten Betreibers gehoert zum Angebot -- das
        // ist die Annahme mit Verfallsdatum aus dem BACKLOG (FB-043).
        $ofOtherOperator = Funnel::factory()->create([
            'tenant_id' => $otherOperator->getKey(),
            'name' => 'Zahnvorsorge',
            'status' => FunnelStatus::PUBLISHED,
        ]);

        $draft = Funnel::factory()->create([
            'tenant_id' => $operator->getKey(),
            'name' => 'Entwurf',
            'status' => FunnelStatus::DRAFT,
        ]);

        $archived = Funnel::factory()->create([
            'tenant_id' => $operator->getKey(),
            'name' => 'Archiviert',
            'status' => FunnelStatus::ARCHIVED,
        ]);

        // Ein Kaeufer-Mandant, der sich selbst einen Funnel anlegt, darf im
        // Angebot nie auftauchen.
        $ofBuyer = Funnel::factory()->create([
            'tenant_id' => $buyer->getKey(),
            'name' => 'Fremdangebot eines Kaeufers',
            'status' => FunnelStatus::PUBLISHED,
        ]);

        $catalog = app(MarketplaceCatalog::class)->publishedFunnels();

        $this->assertSame(
            [
                $published->getKey() => 'Pfotencheck',
                $ofOtherOperator->getKey() => 'Zahnvorsorge',
            ],
            $catalog,
        );

        foreach ([$draft, $archived, $ofBuyer] as $hidden) {
            $this->assertArrayNotHasKey($hidden->getKey(), $catalog);
        }
    }

    public function test_the_catalog_exposes_nothing_beyond_identifier_and_name(): void
    {
        $operator = Tenant::factory()->operator()->create();

        Funnel::factory()->create([
            'tenant_id' => $operator->getKey(),
            'name' => 'Pfotencheck',
            'status' => FunnelStatus::PUBLISHED,
            'lead_price' => 42.50,
            'settings' => ['geheim' => 'Betreiberinterna'],
        ]);

        $catalog = app(MarketplaceCatalog::class)->publishedFunnels();

        // Kennung => Name, sonst nichts. Ein zusaetzliches Feld waere hier
        // sofort sichtbar -- und es waere allen Kaeufern ueber alle Betreiber
        // hinweg freigegeben.
        $this->assertSame(['Pfotencheck'], array_values($catalog));

        foreach (array_keys($catalog) as $key) {
            $this->assertIsInt($key);
        }

        $serialized = json_encode($catalog);

        $this->assertStringNotContainsString('42.5', (string) $serialized);
        $this->assertStringNotContainsString('Betreiberinterna', (string) $serialized);
        $this->assertStringNotContainsString('public_token', (string) $serialized);
    }
}
