<?php

namespace Tests\Feature\Funnel;

use App\Exceptions\FunnelConcurrentlyModified;
use App\Models\Funnel;
use App\Models\FunnelQuestion;
use App\Models\FunnelStep;
use App\Services\FunnelBuilderService;
use Tests\Feature\FeatureTest;

/**
 * FB-015: Der Builder speichert automatisch. Zwei gleichzeitige Bearbeiter
 * duerfen sich dabei nicht gegenseitig ueberschreiben - der Verlust faellt sonst
 * erst auf, wenn der Funnel bereits falsch veroeffentlicht ist.
 */
class FunnelBuilderConflictTest extends FeatureTest
{
    public function test_a_question_is_not_overwritten_by_a_stale_editor(): void
    {
        $service = app(FunnelBuilderService::class);
        $question = $this->question();

        // Bearbeiter A und B oeffnen denselben Stand.
        $seenByA = $service->stampOf($question);
        $seenByB = $seenByA;

        $service->updateQuestion($question, ['label' => 'Von A geaendert'], $seenByA);

        // B speichert auf dem inzwischen veralteten Stand.
        try {
            $service->updateQuestion($question->fresh(), ['label' => 'Von B geaendert'], $seenByB);
            $this->fail('Der veraltete Speichervorgang haette abgewiesen werden muessen.');
        } catch (FunnelConcurrentlyModified) {
            // erwartet
        }

        $this->assertSame('Von A geaendert', $question->fresh()->label);
    }

    public function test_the_same_editor_can_keep_saving(): void
    {
        $service = app(FunnelBuilderService::class);
        $question = $this->question();

        $question = $service->updateQuestion($question, ['label' => 'Erster Wurf'], $service->stampOf($question));
        $question = $service->updateQuestion($question, ['label' => 'Zweiter Wurf'], $service->stampOf($question));

        $this->assertSame('Zweiter Wurf', $question->fresh()->label);
    }

    public function test_two_saves_within_the_same_second_are_distinguishable(): void
    {
        $service = app(FunnelBuilderService::class);
        $question = $this->question();

        $stale = $service->stampOf($question);
        $service->updateQuestion($question, ['label' => 'Sofort danach'], $stale);

        // Beide Speichervorgaenge liegen in derselben Sekunde. Ohne
        // Millisekunden in updated_at waere der Konflikt hier unsichtbar.
        $this->expectException(FunnelConcurrentlyModified::class);

        $service->updateQuestion($question->fresh(), ['label' => 'Zu spaet'], $stale);
    }

    public function test_a_step_is_not_overwritten_by_a_stale_editor(): void
    {
        $service = app(FunnelBuilderService::class);
        $step = $this->question()->step;

        $stale = $service->stampOf($step);
        $service->updateStep($step, ['title' => 'Von A geaendert'], $stale);

        $this->expectException(FunnelConcurrentlyModified::class);

        $service->updateStep($step->fresh(), ['title' => 'Von B geaendert'], $stale);
    }

    private function question(): FunnelQuestion
    {
        $funnel = Funnel::factory()->for($this->createTenant())->create();
        $step = FunnelStep::factory()->for($funnel)->create();

        return FunnelQuestion::factory()->for($step, 'step')->create(['funnel_id' => $funnel->id]);
    }
}
