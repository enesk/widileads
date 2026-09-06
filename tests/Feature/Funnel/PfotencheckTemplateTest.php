<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PublishFunnel;
use App\Constants\FunnelStatus;
use App\Constants\TenantType;
use App\Models\Tenant;
use App\Services\FunnelTemplateImporter;
use Tests\Feature\FeatureTest;

/**
 * FB-019: Die Vorlage „Pfotencheck".
 *
 * Der eigentliche Wert des Tickets steckt in genau dieser Zusage: aus der
 * Vorlage entsteht ein Funnel, der sich ohne Beanstandung veroeffentlichen
 * laesst. Damit ist am realistischen Beispiel belegt, dass Schema (FB-010),
 * Scoring und Bereichspruefung (FB-013) und Publish (FB-014) zusammenpassen --
 * insbesondere, dass die drei Ergebnisbereiche den gesamten erreichbaren
 * Punktebereich abdecken.
 */
class PfotencheckTemplateTest extends FeatureTest
{
    public function test_the_pfotencheck_template_imports_and_publishes_without_complaints(): void
    {
        $tenant = Tenant::factory()->create(['type' => TenantType::OPERATOR]);

        $funnel = app(FunnelTemplateImporter::class)->import('pfotencheck', $tenant);

        $this->assertSame(FunnelStatus::DRAFT, $funnel->status, 'Der Import legt einen Entwurf an.');

        // Wirft FunnelNotPublishableException, sobald ein Schritt, ein
        // Kontaktfeld oder ein Ergebnisbereich fehlt.
        $version = app(PublishFunnel::class)->handle($funnel);

        $this->assertSame(1, $version->version);
        $this->assertSame(FunnelStatus::PUBLISHED, $funnel->fresh()->status);
        $this->assertSame($version->id, $funnel->fresh()->current_version_id);
    }
}
