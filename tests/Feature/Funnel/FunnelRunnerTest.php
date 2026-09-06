<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PublishFunnel;
use App\Constants\FunnelStatus;
use App\Constants\QuestionType;
use App\Dto\FunnelSubmissionData;
use App\Funnel\Runtime\SubmissionReceiver;
use App\Livewire\Funnel\FunnelRunner;
use App\Models\Funnel;
use App\Models\FunnelOption;
use App\Models\FunnelQuestion;
use App\Models\FunnelResult;
use App\Models\FunnelStep;
use App\Models\FunnelVersion;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Feature\FeatureTest;

/**
 * FB-020: Oeffentliche Funnel-Strecke unter /f/{token}.
 */
class FunnelRunnerTest extends FeatureTest
{
    /**
     * Pfotencheck-artiger Funnel: Frage mit Punkten, Ergebnis-Screen,
     * Kontaktschritt.
     */
    private function publishedFunnel(): Funnel
    {
        $funnel = Funnel::factory()->create(['name' => 'Pfotencheck', 'contact_step_position' => 2]);

        $questionStep = FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'position' => 1, 'title' => 'Dein Tier']);
        $question = FunnelQuestion::factory()->create([
            'step_id' => $questionStep->id,
            'field_key' => 'tierart',
            'type' => QuestionType::SINGLE_CHOICE,
            'label' => 'Welches Tier?',
            'position' => 1,
        ]);
        FunnelOption::factory()->create(['question_id' => $question->id, 'value' => 'hund', 'label' => 'Hund', 'score' => 5, 'position' => 1]);
        FunnelOption::factory()->create(['question_id' => $question->id, 'value' => 'katze', 'label' => 'Katze', 'score' => 1, 'position' => 2]);

        $contactStep = FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'position' => 2, 'title' => 'Kontakt']);
        FunnelQuestion::factory()->create([
            'step_id' => $contactStep->id,
            'field_key' => 'email',
            'type' => QuestionType::EMAIL,
            'label' => 'E-Mail-Adresse',
            'position' => 1,
        ]);
        FunnelQuestion::factory()->create([
            'step_id' => $contactStep->id,
            'field_key' => 'telefon',
            'type' => QuestionType::PHONE,
            'label' => 'Telefonnummer',
            'position' => 2,
        ]);

        FunnelResult::factory()->forScoreRange(0, 3)->create(['funnel_id' => $funnel->id, 'title' => 'Geringes Risiko']);
        FunnelResult::factory()->forScoreRange(4, 5)->create(['funnel_id' => $funnel->id, 'title' => 'Hohes Risiko']);

        app(PublishFunnel::class)->handle($funnel);

        return $funnel->refresh();
    }

    public function test_a_visitor_walks_the_published_funnel_to_the_result_and_hands_over_the_submission(): void
    {
        $funnel = $this->publishedFunnel();

        // Die Seite muss sich ueberhaupt ausliefern lassen -- kein Layout- oder
        // Komponententest, sondern die Absicherung, dass die Strecke nicht mit
        // einem Fehler startet.
        $this->get('/f/'.$funnel->public_token)->assertOk()->assertSee('Welches Tier?');

        // Der Empfaenger merkt sich die Uebergabe; FB-031 haengt sich spaeter
        // genau hier ein.
        $box = new stdClass;
        $box->submission = null;

        $this->app->bind(SubmissionReceiver::class, fn (): SubmissionReceiver => new class($box) implements SubmissionReceiver
        {
            public function __construct(private readonly stdClass $box) {}

            public function receive(FunnelSubmissionData $submission): void
            {
                $this->box->submission = $submission;
            }
        });

        Livewire::test(FunnelRunner::class, ['token' => $funnel->public_token])
            ->assertSee('Welches Tier?')
            ->set('answers.tierart', 'hund')
            ->call('submitStep')
            // Vor dem Kontaktschritt steht der Ergebnis-Screen.
            ->assertSet('phase', 'result')
            ->assertSee('Hohes Risiko')
            ->call('continueAfterResult')
            ->assertSet('phase', 'contact')
            ->assertSee('E-Mail-Adresse')
            ->set('answers.email', 'Anna@Example.COM')
            ->set('answers.telefon', '0151 12345678')
            ->call('submitContact')
            ->assertSet('phase', 'done')
            ->assertHasNoErrors();

        $received = $box->submission;

        $this->assertInstanceOf(FunnelSubmissionData::class, $received);
        $this->assertSame($funnel->public_token, $received->publicToken);
        $this->assertSame($funnel->current_version_id, $received->funnelVersionId);
        $this->assertSame(5, $received->score);
        $this->assertSame('4-5', $received->resultKey);
        $this->assertSame([1, 2], $received->visitedStepPositions);
        // Normalisiert ueber die Fragetyp-Handler aus FB-011.
        $this->assertSame('anna@example.com', $received->answers['email']);
        $this->assertSame('+4915112345678', $received->answers['telefon']);
    }

    public function test_invalid_answers_are_rejected_and_the_step_does_not_advance(): void
    {
        $funnel = $this->publishedFunnel();

        Livewire::test(FunnelRunner::class, ['token' => $funnel->public_token])
            // Pflichtfrage ohne Antwort.
            ->call('submitStep')
            ->assertHasErrors(['answers.tierart'])
            ->assertSet('phase', 'questions')
            // Wert, den es im Snapshot nicht gibt.
            ->set('answers.tierart', 'einhorn')
            ->call('submitStep')
            ->assertHasErrors(['answers.tierart'])
            ->assertSet('phase', 'questions')
            // Gueltige Auswahl bringt die Strecke weiter.
            ->set('answers.tierart', 'katze')
            ->call('submitStep')
            ->assertHasNoErrors()
            ->assertSet('phase', 'result');
    }

    public function test_contact_step_validates_email_and_phone(): void
    {
        $funnel = $this->publishedFunnel();

        Livewire::test(FunnelRunner::class, ['token' => $funnel->public_token])
            ->set('answers.tierart', 'hund')
            ->call('submitStep')
            ->call('continueAfterResult')
            ->set('answers.email', 'keine-adresse')
            ->set('answers.telefon', '')
            ->call('submitContact')
            ->assertHasErrors(['answers.email', 'answers.telefon'])
            ->assertSet('phase', 'contact');
    }

    public function test_unknown_or_unpublished_tokens_lead_to_404(): void
    {
        // FeatureTest schaltet die Exception-Behandlung global ab; fuer echte
        // HTTP-Statuscodes wird sie hier gebraucht.
        $this->withExceptionHandling();

        // Nie veroeffentlichter Funnel.
        $draft = Funnel::factory()->create();

        // Token, den es nicht gibt.
        $this->get('/f/'.Str::ulid())->assertNotFound();
        $this->get('/f/'.$draft->public_token)->assertNotFound();
    }

    public function test_an_archived_funnel_shows_a_notice_instead_of_the_questions(): void
    {
        $funnel = $this->publishedFunnel();
        $funnel->forceFill(['status' => FunnelStatus::ARCHIVED])->save();

        Livewire::test(FunnelRunner::class, ['token' => $funnel->public_token])
            ->assertSee(__('runtime.archived_title'))
            ->assertDontSee('Welches Tier?');

        // Ohne Hinweisseite bleibt nur 404.
        $this->withExceptionHandling();
        config()->set('funnel.runtime_public.show_notice_for_archived', false);

        $this->get('/f/'.$funnel->public_token)->assertNotFound();
    }

    public function test_the_runtime_reads_the_snapshot_and_not_the_live_tables(): void
    {
        $funnel = $this->publishedFunnel();

        // Entwurf umbauen: Die laufende Strecke darf das nicht mitbekommen.
        $funnel->questions()->where('field_key', 'tierart')->sole()->update(['label' => 'Nachtraeglich geaendert']);
        FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'position' => 3, 'title' => 'Neuer Schritt']);

        Livewire::test(FunnelRunner::class, ['token' => $funnel->public_token])
            ->assertSee('Welches Tier?')
            ->assertDontSee('Nachtraeglich geaendert');

        $this->assertSame(1, FunnelVersion::query()->where('funnel_id', $funnel->id)->count());
    }
}
