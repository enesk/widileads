<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PublishFunnel;
use App\Constants\QuestionType;
use App\Constants\SessionEventType;
use App\Livewire\Funnel\FunnelRunner;
use App\Models\Funnel;
use App\Models\FunnelOption;
use App\Models\FunnelQuestion;
use App\Models\FunnelResult;
use App\Models\FunnelStep;
use App\Models\PublicSession;
use App\Services\PublicSessionService;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * FB-021: Sitzung und Ereignisprotokoll der oeffentlichen Strecke.
 */
class PublicSessionTest extends FeatureTest
{
    private function publishedFunnel(): Funnel
    {
        $funnel = Funnel::factory()->create(['name' => 'Pfotencheck', 'contact_step_position' => 2]);

        $step = FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'position' => 1]);
        $question = FunnelQuestion::factory()->create([
            'step_id' => $step->id,
            'field_key' => 'tierart',
            'type' => QuestionType::SINGLE_CHOICE,
            'label' => 'Welches Tier?',
            'position' => 1,
        ]);
        FunnelOption::factory()->create(['question_id' => $question->id, 'value' => 'hund', 'score' => 5, 'position' => 1]);
        FunnelOption::factory()->create(['question_id' => $question->id, 'value' => 'katze', 'score' => 1, 'position' => 2]);

        $contactStep = FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'position' => 2]);
        FunnelQuestion::factory()->create([
            'step_id' => $contactStep->id,
            'field_key' => 'email',
            'type' => QuestionType::EMAIL,
            'label' => 'E-Mail-Adresse',
            'position' => 1,
        ]);

        FunnelResult::factory()->forScoreRange(0, 5)->create(['funnel_id' => $funnel->id, 'title' => 'Ergebnis']);

        app(PublishFunnel::class)->handle($funnel);

        return $funnel->refresh();
    }

    public function test_partial_progress_survives_a_reload(): void
    {
        $funnel = $this->publishedFunnel();

        $first = Livewire::test(FunnelRunner::class, ['token' => $funnel->public_token])
            ->set('answers.tierart', 'hund')
            ->call('submitStep');

        $sessionToken = $first->get('sessionToken');
        $session = PublicSession::query()->where('token', $sessionToken)->sole();

        $this->assertSame(['tierart' => 'hund'], $session->answers);
        // Die Sitzung steht auf dem Schritt, an dem es weitergeht -- der
        // Ergebnis-Screen davor ist eine Zwischenanzeige, kein eigener Schritt.
        $this->assertSame(2, $session->current_step);

        // Neuer Aufruf mit demselben Cookie: dieselbe Sitzung, Antworten wieder da.
        Cookie::queue('funnel_session_'.$funnel->public_token, $sessionToken, 60);

        $second = Livewire::withCookie('funnel_session_'.$funnel->public_token, $sessionToken)
            ->test(FunnelRunner::class, ['token' => $funnel->public_token]);

        $this->assertSame($sessionToken, $second->get('sessionToken'));
        $this->assertSame(['tierart' => 'hund'], $second->get('answers'));
        $this->assertSame(1, PublicSession::query()->count(), 'Ein Reload darf keine zweite Sitzung anlegen.');
    }

    public function test_events_are_written_in_the_order_they_happen(): void
    {
        $funnel = $this->publishedFunnel();

        $component = Livewire::test(FunnelRunner::class, ['token' => $funnel->public_token])
            ->set('answers.tierart', 'hund')
            ->call('submitStep')
            ->call('continueAfterResult')
            ->set('answers.email', 'anna@example.com')
            ->call('submitContact');

        $session = PublicSession::query()->where('token', $component->get('sessionToken'))->sole();
        $types = $session->events()->pluck('type')->map(fn (SessionEventType $type): string => $type->value)->all();

        $this->assertSame([
            'view',
            'step_view',      // Schritt 1 angezeigt
            'step_complete',  // Schritt 1 beantwortet
            'step_view',      // Kontaktschritt angezeigt (Ergebnis-Screen davor)
            'step_complete',  // Kontaktdaten abgeschickt
            'submit',
        ], $types);

        $this->assertNotNull($session->completed_at);
        $this->assertSame([1, 2], $session->load('events')->visitedStepPositions());
    }

    public function test_only_really_inactive_sessions_are_marked_as_abandoned(): void
    {
        config()->set('funnel.public.abandon_after_minutes', 30);

        $stale = PublicSession::factory()->inactiveFor(31)->create();
        $recent = PublicSession::factory()->inactiveFor(5)->create();
        $completed = PublicSession::factory()->inactiveFor(120)->completed()->create();

        $abandoned = app(PublicSessionService::class)->abandonStale();

        $this->assertSame(1, $abandoned);
        $this->assertNotNull($stale->refresh()->abandoned_at);
        $this->assertNull($recent->refresh()->abandoned_at);
        $this->assertNull($completed->refresh()->abandoned_at, 'Eine abgeschlossene Anfrage ist kein Abbruch.');

        $this->assertSame(
            SessionEventType::ABANDON,
            $stale->events()->latest('id')->sole()->type,
        );

        // Ein zweiter Lauf markiert nichts doppelt.
        $this->assertSame(0, app(PublicSessionService::class)->abandonStale());
    }

    public function test_a_returning_visitor_continues_an_abandoned_session(): void
    {
        $funnel = $this->publishedFunnel();

        $component = Livewire::test(FunnelRunner::class, ['token' => $funnel->public_token])
            ->set('answers.tierart', 'katze')
            ->call('submitStep');

        $sessionToken = $component->get('sessionToken');
        $session = PublicSession::query()->where('token', $sessionToken)->sole();
        $session->forceFill(['last_activity_at' => now()->subHour()])->save();

        app(PublicSessionService::class)->abandonStale();

        $this->assertNotNull($session->refresh()->abandoned_at);

        // Der Endkunde kommt zurueck -- dieselbe Sitzung laeuft weiter.
        $resumed = Livewire::withCookie('funnel_session_'.$funnel->public_token, $sessionToken)
            ->test(FunnelRunner::class, ['token' => $funnel->public_token]);

        $this->assertSame($sessionToken, $resumed->get('sessionToken'));
        $this->assertSame(['tierart' => 'katze'], $resumed->get('answers'));
        $this->assertNull($session->refresh()->abandoned_at);
        $this->assertSame(1, PublicSession::query()->count());
    }
}
