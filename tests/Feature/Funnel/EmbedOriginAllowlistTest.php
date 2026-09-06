<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PublishFunnel;
use App\Constants\AuditAction;
use App\Constants\QuestionType;
use App\Models\AuditLog;
use App\Models\Funnel;
use App\Models\FunnelOrigin;
use App\Models\FunnelQuestion;
use App\Models\FunnelResult;
use App\Models\FunnelStep;
use Tests\Feature\FeatureTest;

/**
 * FB-025: Nur freigegebene Seiten duerfen einen Funnel einbetten.
 *
 * Ohne Allowlist koennte jede fremde Seite den Funnel in ihre eigene einbetten
 * und Leads unter ihrem Namen sammeln -- der Betreiber saehe nur, dass Anfragen
 * kommen, nicht von wo.
 */
class EmbedOriginAllowlistTest extends FeatureTest
{
    private function publishedFunnel(): Funnel
    {
        $funnel = Funnel::factory()->create(['name' => 'Pfotencheck']);

        $step = FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'position' => 1]);
        FunnelQuestion::factory()->create([
            'step_id' => $step->id,
            'field_key' => 'email',
            'type' => QuestionType::EMAIL,
            'label' => 'E-Mail-Adresse',
            'position' => 1,
        ]);
        FunnelResult::factory()->forScoreRange(0, 0)->create(['funnel_id' => $funnel->id]);

        app(PublishFunnel::class)->handle($funnel);

        return $funnel->refresh();
    }

    public function test_a_listed_origin_may_embed_and_the_own_domain_always_may(): void
    {
        $funnel = $this->publishedFunnel();

        FunnelOrigin::factory()->create([
            'funnel_id' => $funnel->id,
            // Mit Pfad eingetragen -- gespeichert wird die reine Herkunft.
            'origin' => 'https://tierarztportal.com/pfotencheck',
        ]);

        $this->assertSame('https://tierarztportal.com', $funnel->origins()->sole()->origin);

        $this->get('/f/'.$funnel->public_token.'?embed=1&origin=https://tierarztportal.com')
            ->assertOk();

        // Die eigene Domain braucht keinen Eintrag -- Vorschau und Testseite
        // laufen darueber.
        $this->get('/f/'.$funnel->public_token.'?embed=1&origin='.config('app.url'))
            ->assertOk();

        // Ohne Herkunft ist es keine Einbettung, sondern ein direkter Aufruf.
        $this->get('/f/'.$funnel->public_token)->assertOk();
    }

    public function test_an_unlisted_origin_is_rejected_with_403_and_recorded(): void
    {
        $this->withExceptionHandling();

        $funnel = $this->publishedFunnel();
        FunnelOrigin::factory()->create(['funnel_id' => $funnel->id, 'origin' => 'https://tierarztportal.com']);

        AuditLog::query()->getQuery()->delete();

        $this->get('/f/'.$funnel->public_token.'?embed=1&origin=https://fremde-seite.example')
            ->assertForbidden();

        // Der Versuch bleibt sichtbar: Der Besucher sieht nur eine Fehlerseite,
        // der Betreiber soll erfahren, wer seinen Funnel einbetten wollte.
        $entry = AuditLog::query()
            ->where('action', AuditAction::EMBED_ORIGIN_REJECTED->value)
            ->sole();

        $this->assertSame($funnel->tenant_id, $entry->tenant_id);
        $this->assertSame(Funnel::class, $entry->subject_type);
        $this->assertSame('https://fremde-seite.example', $entry->payload['rejected_origin']);
        $this->assertSame($funnel->public_token, $entry->payload['funnel_public_token']);

        // Ein Subdomain-Treffer reicht nicht: freigegeben ist genau eine Herkunft.
        $this->get('/f/'.$funnel->public_token.'?embed=1&origin=https://evil.tierarztportal.com')
            ->assertForbidden();

        // Und auch ein anderes Schema ist eine andere Herkunft.
        $this->get('/f/'.$funnel->public_token.'?embed=1&origin=http://tierarztportal.com')
            ->assertForbidden();
    }
}
