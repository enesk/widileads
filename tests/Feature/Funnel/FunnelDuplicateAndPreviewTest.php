<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\CreateFunnelPreviewLink;
use App\Actions\DuplicateFunnel;
use App\Constants\FunnelStatus;
use App\Constants\TenantType;
use App\Models\Funnel;
use App\Models\Tenant;
use App\Services\FunnelTemplateImporter;
use Illuminate\Support\Carbon;
use Tests\Feature\FeatureTest;

/**
 * FB-018: Duplizieren und Vorschau.
 *
 * Als Vorlage dient der Pfotencheck aus FB-019 -- ein Funnel mit sieben
 * Schritten, zwoelf Fragen, Punkten und drei Ergebnisbereichen. An einem
 * kuenstlich kleinen Funnel wuerde eine unvollstaendige Kopie nicht auffallen.
 */
class FunnelDuplicateAndPreviewTest extends FeatureTest
{
    private function pfotencheck(): Funnel
    {
        $tenant = Tenant::factory()->create(['type' => TenantType::OPERATOR]);

        return app(FunnelTemplateImporter::class)->import('pfotencheck', $tenant);
    }

    public function test_a_duplicate_is_complete_and_carries_its_own_token(): void
    {
        $original = $this->pfotencheck();

        $copy = app(DuplicateFunnel::class)->handle($original);

        $this->assertNotSame($original->public_token, $copy->public_token, 'Die Kopie braucht einen eigenen Token.');
        $this->assertNotSame($original->slug, $copy->slug);
        $this->assertSame(FunnelStatus::DRAFT, $copy->status);
        $this->assertSame($original->tenant_id, $copy->tenant_id);

        // Tiefe Kopie: jede Ebene muss mitgekommen sein.
        $this->assertSame($original->steps()->count(), $copy->steps()->count());
        $this->assertSame($original->questions()->count(), $copy->questions()->count());
        $this->assertSame($original->results()->count(), $copy->results()->count());
        $this->assertSame(
            $original->questions()->withCount('options')->get()->sum('options_count'),
            $copy->questions()->withCount('options')->get()->sum('options_count'),
        );

        // Und keine Zeile der Kopie darf auf das Original zeigen.
        $this->assertSame(0, $copy->steps()->whereIn('id', $original->steps()->pluck('id'))->count());
    }

    public function test_the_preview_shows_a_draft_that_was_never_published(): void
    {
        $funnel = $this->pfotencheck();

        $this->assertNull($funnel->current_version_id, 'Der Entwurf ist noch nie veroeffentlicht worden.');

        $this->get(app(CreateFunnelPreviewLink::class)->handle($funnel))
            ->assertOk()
            ->assertSee($funnel->name);

        // Ohne Signatur bleibt die Vorschau verschlossen. FeatureTest schaltet
        // die Fehlerbehandlung ab -- fuer die Antwort statt der Ausnahme wieder an.
        $this->withExceptionHandling();

        $this->get(route('funnel.preview', ['token' => $funnel->public_token], absolute: false))
            ->assertForbidden();
    }

    public function test_an_expired_preview_link_is_rejected(): void
    {
        $funnel = $this->pfotencheck();
        $link = app(CreateFunnelPreviewLink::class)->handle($funnel);

        $ttl = (int) config('funnel.preview.link_ttl_minutes');

        $this->withExceptionHandling();

        Carbon::setTestNow(now()->addMinutes($ttl + 1));

        try {
            $this->get($link)->assertForbidden();
        } finally {
            Carbon::setTestNow();
        }
    }
}
