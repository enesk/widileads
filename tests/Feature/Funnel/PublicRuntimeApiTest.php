<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PublishFunnel;
use App\Constants\AuditAction;
use App\Constants\QuestionType;
use App\Dto\FunnelSubmissionData;
use App\Funnel\Runtime\SubmissionReceiver;
use App\Models\AuditLog;
use App\Models\Funnel;
use App\Models\FunnelOption;
use App\Models\FunnelOrigin;
use App\Models\FunnelQuestion;
use App\Models\FunnelResult;
use App\Models\FunnelStep;
use App\Models\PublicSession;
use Illuminate\Support\Str;
use stdClass;
use Tests\Feature\FeatureTest;

/**
 * FB-026: Headless-Runtime-API fuer eigene Frontends.
 *
 * Die API benutzt denselben FunnelRunService wie die eigene Strecke -- ein
 * fremdes Frontend nimmt damit denselben Weg, statt gegen eine zweite Runtime
 * zu laufen, die langsam auseinanderdriftet.
 */
class PublicRuntimeApiTest extends FeatureTest
{
    private function publishedFunnel(): Funnel
    {
        $funnel = Funnel::factory()->create(['name' => 'Pfotencheck', 'contact_step_position' => 2]);

        $step = FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'position' => 1, 'title' => 'Dein Tier']);
        $question = FunnelQuestion::factory()->create([
            'step_id' => $step->id,
            'field_key' => 'tierart',
            'type' => QuestionType::SINGLE_CHOICE,
            'label' => 'Welches Tier?',
            'position' => 1,
        ]);
        FunnelOption::factory()->create(['question_id' => $question->id, 'value' => 'hund', 'score' => 5, 'position' => 1]);
        FunnelOption::factory()->create(['question_id' => $question->id, 'value' => 'katze', 'score' => 1, 'position' => 2]);

        $contact = FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'position' => 2, 'title' => 'Kontakt']);
        FunnelQuestion::factory()->create([
            'step_id' => $contact->id,
            'field_key' => 'email',
            'type' => QuestionType::EMAIL,
            'label' => 'E-Mail-Adresse',
            'position' => 1,
        ]);
        FunnelQuestion::factory()->create([
            'step_id' => $contact->id,
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

    public function test_a_foreign_frontend_walks_the_whole_funnel(): void
    {
        $funnel = $this->publishedFunnel();
        $box = $this->captureSubmission();

        // 1. Struktur laden -- ohne Datenbank-IDs, adressiert wird ueber
        //    Schrittposition und Feldschluessel.
        $structure = $this->getJson('/api/public/v1/funnels/'.$funnel->public_token)
            ->assertOk()
            ->json('data');

        $this->assertSame($funnel->public_token, $structure['funnel']['public_token']);
        $this->assertSame(1, $structure['version']);
        $this->assertCount(2, $structure['steps']);
        $this->assertSame('tierart', $structure['steps'][0]['questions'][0]['field_key']);
        $this->assertStringNotContainsString('"id"', (string) json_encode($structure['steps']));

        // 2. Sitzung beginnen.
        $session = $this->postJson('/api/public/v1/funnels/'.$funnel->public_token.'/sessions')
            ->assertCreated()
            ->json('data');

        $this->assertSame(26, strlen($session['session_token']));
        $this->assertSame(1, $session['step']['position']);

        $base = '/api/public/v1/funnels/'.$funnel->public_token.'/sessions/'.$session['session_token'];

        // 3. Antwort schicken -- die Antwort nennt den naechsten Schritt.
        $afterStep = $this->patchJson($base.'/answers', ['answers' => ['tierart' => 'hund']])
            ->assertOk()
            ->json('data');

        // Vor dem Kontaktschritt kommt das Ergebnis, wie in der eigenen Strecke.
        $this->assertSame('result', $afterStep['phase']);
        $this->assertSame('Hohes Risiko', $afterStep['result']['title']);
        $this->assertSame('4-5', $afterStep['result']['key']);
        $this->assertSame(5, $afterStep['score']);

        // 4. Absenden.
        $done = $this->postJson($base.'/submit', [
            'answers' => ['email' => 'Anna@Example.COM', 'telefon' => '0151 12345678'],
        ])->assertOk()->json('data');

        $this->assertSame('done', $done['phase']);
        $this->assertTrue($done['completed']);

        /** @var FunnelSubmissionData $submission */
        $submission = $box->submission;

        $this->assertNotNull($submission);
        $this->assertSame($funnel->public_token, $submission->publicToken);
        $this->assertSame('4-5', $submission->resultKey);
        // Normalisiert ueber dieselben Fragetyp-Handler wie die eigene Strecke.
        $this->assertSame('anna@example.com', $submission->answers['email']);
        $this->assertSame('+4915112345678', $submission->answers['telefon']);
    }

    public function test_invalid_answers_are_rejected_with_422_and_the_session_does_not_advance(): void
    {
        $this->withExceptionHandling();

        $funnel = $this->publishedFunnel();

        $token = $this->postJson('/api/public/v1/funnels/'.$funnel->public_token.'/sessions')
            ->json('data.session_token');

        $base = '/api/public/v1/funnels/'.$funnel->public_token.'/sessions/'.$token;

        // Wert, den es im Snapshot nicht gibt.
        $this->patchJson($base.'/answers', ['answers' => ['tierart' => 'einhorn']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('answers.tierart');

        $this->assertSame(1, PublicSession::query()->where('token', $token)->sole()->current_step);

        // Unbrauchbare Kontaktdaten werden ebenfalls abgewiesen -- ein fremdes
        // Frontend darf sie nicht ungeprueft einliefern.
        $this->patchJson($base.'/answers', ['answers' => ['tierart' => 'hund']])->assertOk();

        $this->postJson($base.'/submit', ['answers' => ['email' => 'keine-adresse', 'telefon' => '123']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers.email', 'answers.telefon']);

        $this->assertNull(PublicSession::query()->where('token', $token)->sole()->completed_at);
    }

    public function test_unknown_funnels_and_foreign_sessions_are_not_found(): void
    {
        $this->withExceptionHandling();

        $funnel = $this->publishedFunnel();
        $otherFunnel = $this->publishedFunnel();

        $this->getJson('/api/public/v1/funnels/'.Str::ulid())->assertNotFound();
        $this->getJson('/api/public/v1/funnels/'.Funnel::factory()->create()->public_token)->assertNotFound();

        $token = $this->postJson('/api/public/v1/funnels/'.$funnel->public_token.'/sessions')
            ->json('data.session_token');

        // Ein Sitzungstoken der einen Strecke gilt nicht in der anderen.
        $this->patchJson(
            '/api/public/v1/funnels/'.$otherFunnel->public_token.'/sessions/'.$token.'/answers',
            ['answers' => ['tierart' => 'hund']],
        )->assertNotFound();
    }

    public function test_an_unlisted_origin_is_rejected_and_recorded(): void
    {
        // FeatureTest schaltet die Exception-Behandlung ab; fuer echte
        // HTTP-Statuscodes wird sie hier gebraucht.
        $this->withExceptionHandling();

        $funnel = $this->publishedFunnel();
        FunnelOrigin::factory()->create(['funnel_id' => $funnel->id, 'origin' => 'https://tierarztportal.com']);

        AuditLog::query()->getQuery()->delete();

        // Erlaubte Herkunft kommt durch.
        $this->getJson('/api/public/v1/funnels/'.$funnel->public_token, ['Origin' => 'https://tierarztportal.com'])
            ->assertOk();

        // Fremde Herkunft: 403 und ein Eintrag im Audit-Log.
        $this->getJson('/api/public/v1/funnels/'.$funnel->public_token, ['Origin' => 'https://fremde-seite.example'])
            ->assertForbidden();

        $entry = AuditLog::query()->where('action', AuditAction::EMBED_ORIGIN_REJECTED->value)->sole();

        $this->assertSame('https://fremde-seite.example', $entry->payload['rejected_origin']);

        // Ohne Origin-Kopf ist es kein Aufruf aus einer fremden Seite -- etwa
        // ein Server-zu-Server-Aufruf -- und bleibt erlaubt.
        $this->getJson('/api/public/v1/funnels/'.$funnel->public_token)->assertOk();
    }
}
