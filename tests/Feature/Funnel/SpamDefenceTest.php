<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PublishFunnel;
use App\Constants\QuestionType;
use App\Dto\FunnelSubmissionData;
use App\Funnel\Runtime\DuplicateLeadFinder;
use App\Funnel\Runtime\SubmissionReceiver;
use App\Livewire\Funnel\FunnelRunner;
use App\Models\Funnel;
use App\Models\FunnelQuestion;
use App\Models\FunnelResult;
use App\Models\FunnelStep;
use App\Models\PublicSession;
use Livewire\Livewire;
use stdClass;
use Tests\Feature\FeatureTest;

/**
 * FB-023: Spam-Abwehr.
 *
 * Kernpunkt: Erkannt wird viel, verworfen wird fast nichts. Eine zu Unrecht
 * abgewiesene Anfrage ist verloren, eine zu Unrecht angenommene laesst sich
 * spaeter aussortieren (FB-033).
 */
class SpamDefenceTest extends FeatureTest
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

    /**
     * Faengt die Uebergabe ab, die sonst FB-031 bekommt.
     */
    private function captureSubmission(): stdClass
    {
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

        return $box;
    }

    private function submit(Funnel $funnel, callable $fill): void
    {
        $component = Livewire::test(FunnelRunner::class, ['token' => $funnel->public_token]);

        $fill($component);

        $component->call('submitStep')->call('continueAfterResult');
    }

    public function test_the_time_trap_marks_a_submission_that_arrives_too_fast(): void
    {
        config()->set('funnel.public.min_seconds_before_submit', 30);

        $funnel = $this->publishedFunnel();
        $box = $this->captureSubmission();

        // Sofort abgeschickt -- schneller, als ein Mensch lesen kann.
        $this->submit($funnel, fn ($component) => $component->set('answers.email', 'bot@example.com'));

        /** @var FunnelSubmissionData $submission */
        $submission = $box->submission;

        $this->assertNotNull($submission, 'Die Einreichung darf trotzdem ankommen.');
        $this->assertTrue($submission->spamSignals['submitted_too_fast']);

        // Das Signal steht auch an der Sitzung -- Beweis vor Bewertung.
        $session = PublicSession::query()->findOrFail($submission->publicSessionId);

        $this->assertTrue($session->spam_signals['submitted_too_fast']);
        $this->assertNotNull($session->completed_at);

        // Wer sich Zeit laesst, loest die Falle nicht aus.
        $session->forceFill(['started_at' => now()->subMinutes(5)])->save();
        PublicSession::query()->whereKey($session->id)->update(['completed_at' => null]);

        $box2 = $this->captureSubmission();

        Livewire::withCookie('funnel_session_'.$funnel->public_token, $session->token)
            ->test(FunnelRunner::class, ['token' => $funnel->public_token])
            ->set('answers.email', 'mensch@example.com')
            ->call('submitStep')
            ->call('continueAfterResult');

        $this->assertFalse($box2->submission->spamSignals['submitted_too_fast']);
    }

    public function test_the_honeypot_marks_a_filled_hidden_field(): void
    {
        $funnel = $this->publishedFunnel();
        $box = $this->captureSubmission();

        $this->submit($funnel, function ($component): void {
            $component->set('answers.email', 'bot@example.com')
                // Nur ein Bot fuellt dieses Feld -- fuer Menschen ist es unsichtbar.
                ->set('website', 'http://spam.example.com');
        });

        /** @var FunnelSubmissionData $submission */
        $submission = $box->submission;

        $this->assertNotNull($submission, 'Auch eine verdaechtige Einreichung wird uebergeben.');
        $this->assertTrue($submission->spamSignals['honeypot_tripped']);
        $this->assertTrue(
            PublicSession::query()->findOrFail($submission->publicSessionId)->spam_signals['honeypot_tripped'],
        );
    }

    public function test_a_duplicate_is_handed_over_with_a_reference_instead_of_being_discarded(): void
    {
        $funnel = $this->publishedFunnel();

        // Die Spalten aus FB-031 gibt es noch nicht -- der Finder prueft
        // deshalb nicht und bricht auch nicht ab.
        $finder = app(DuplicateLeadFinder::class);

        $this->assertFalse($finder->leadsTableIsReady());
        $this->assertNull($finder->findRecentDuplicate($funnel->id, ['email' => 'anna@example.com']));

        // Sobald FB-031 die Spalten anlegt, liefert er eine ID. Genau dieser
        // Fall wird hier gestellt: Die Einreichung muss trotzdem ankommen und
        // den Verweis tragen -- verworfen wird nichts.
        $this->app->bind(DuplicateLeadFinder::class, fn (): DuplicateLeadFinder => new class extends DuplicateLeadFinder
        {
            public function leadsTableIsReady(): bool
            {
                return true;
            }

            public function findRecentDuplicate(?int $funnelId, array $answers): ?int
            {
                return ($answers['email'] ?? null) === 'anna@example.com' ? 4711 : null;
            }
        });

        $box = $this->captureSubmission();

        $this->submit($funnel, fn ($component) => $component->set('answers.email', 'Anna@Example.com'));

        /** @var FunnelSubmissionData $submission */
        $submission = $box->submission;

        $this->assertNotNull($submission, 'Eine Dublette wird nicht verworfen.');
        $this->assertSame(4711, $submission->duplicateOfLeadId);
        $this->assertSame(4711, $submission->spamSignals['duplicate_of_lead_id']);

        // Der Lead entsteht ganz normal; ueber Dublette oder Neuanfrage
        // entscheidet FB-033.
        $session = PublicSession::query()->findOrFail($submission->publicSessionId);

        $this->assertNotNull($session->completed_at);
        $this->assertSame(4711, $session->spam_signals['duplicate_of_lead_id']);
    }
}
