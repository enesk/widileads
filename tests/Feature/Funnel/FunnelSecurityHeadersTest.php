<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PublishFunnel;
use App\Constants\TenantType;
use App\Models\Funnel;
use App\Models\FunnelOrigin;
use App\Models\Tenant;
use App\Services\FunnelTemplateImporter;
use Tests\Feature\FeatureTest;

/**
 * Die oeffentliche Strecke sagt dem Browser, wer sie einrahmen darf (FB-043).
 *
 * Die Einbettungs-Allowlist aus FB-025 wurde bisher nur serverseitig
 * durchgesetzt, und zwar ueber den Origin-Kopf. Ein iFrame schickt beim Laden
 * keinen Origin-Kopf -- die Liste griff dort also nicht, und jede fremde Seite
 * konnte die Strecke einrahmen. Der Fehler ist teuer und vollstaendig
 * unsichtbar: Die Seite laedt, sie laedt nur an der falschen Stelle.
 */
class FunnelSecurityHeadersTest extends FeatureTest
{
    public function test_ohne_freigegebene_herkunft_darf_nur_die_eigene_seite_einrahmen(): void
    {
        $funnel = $this->publishedFunnel();

        $response = $this->get('/f/'.$funnel->public_token);

        $response->assertOk();

        $this->assertSame(
            "frame-ancestors 'self'",
            $this->frameAncestors($response->headers->get('Content-Security-Policy')),
        );
    }

    public function test_freigegebene_herkuenfte_stehen_in_frame_ancestors(): void
    {
        $funnel = $this->publishedFunnel();

        FunnelOrigin::query()->create([
            'funnel_id' => $funnel->getKey(),
            'origin' => 'https://tierheim-beispiel.test',
        ]);

        $response = $this->get('/f/'.$funnel->public_token);

        $this->assertSame(
            "frame-ancestors 'self' https://tierheim-beispiel.test",
            $this->frameAncestors($response->headers->get('Content-Security-Policy')),
        );
    }

    private function frameAncestors(?string $policy): string
    {
        foreach (explode(';', (string) $policy) as $directive) {
            $directive = trim($directive);

            if (str_starts_with($directive, 'frame-ancestors')) {
                return $directive;
            }
        }

        return '';
    }

    private function publishedFunnel(): Funnel
    {
        $tenant = Tenant::factory()->create(['type' => TenantType::OPERATOR]);

        $funnel = app(FunnelTemplateImporter::class)->import('pfotencheck', $tenant);

        app(PublishFunnel::class)->handle($funnel);

        return $funnel->refresh();
    }
}
