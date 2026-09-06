<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PublishFunnel;
use App\Constants\QuestionType;
use App\Models\Funnel;
use App\Models\FunnelOrigin;
use App\Models\FunnelQuestion;
use App\Models\FunnelResult;
use App\Models\FunnelStep;
use Tests\Feature\FeatureTest;

/**
 * FB-024: Eingebettete Auslieferung.
 */
class EmbedTest extends FeatureTest
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

    public function test_the_embedded_run_uses_the_minimal_layout_and_reports_its_height(): void
    {
        $funnel = $this->publishedFunnel();

        // Seit FB-025 bettet nur ein, wer freigegeben ist.
        FunnelOrigin::factory()->create([
            'funnel_id' => $funnel->id,
            'origin' => 'https://tierarztportal.com',
        ]);

        $response = $this->get('/f/'.$funnel->public_token.'?embed=1&origin=https://tierarztportal.com')
            ->assertOk();

        // Kein Seitenhintergrund und keine Navigation der Hauptseite.
        $response->assertSee('bg-transparent', false);
        $response->assertDontSee('x-layouts.app', false);
        $response->assertDontSee('cookie-consent', false);

        // Die Hoehenmeldung geht gezielt an die einbettende Seite, nie an "*".
        $response->assertSee('https:\/\/tierarztportal.com', false);
        $response->assertSee('widileads-funnel', false);
        $response->assertDontSee("postMessage(message, '*')", false);

        // Ohne embed=1 bleibt es die eigenstaendige Seite.
        $this->get('/f/'.$funnel->public_token)
            ->assertOk()
            ->assertSee('bg-base-200', false)
            ->assertDontSee('widileads-funnel', false);
    }
}
