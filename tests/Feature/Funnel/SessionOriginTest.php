<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PublishFunnel;
use App\Constants\QuestionType;
use App\Livewire\Funnel\FunnelRunner;
use App\Models\Funnel;
use App\Models\FunnelQuestion;
use App\Models\FunnelResult;
use App\Models\FunnelStep;
use App\Models\PublicSession;
use App\Services\IpHasher;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * FB-022: Herkunft einer oeffentlichen Sitzung.
 */
class SessionOriginTest extends FeatureTest
{
    private const IP_ADDRESS = '203.0.113.42';

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

    public function test_campaign_parameters_and_referrer_are_taken_from_the_request(): void
    {
        $funnel = $this->publishedFunnel();

        $this->withServerVariables([
            'HTTP_REFERER' => 'https://tierarztportal.com/pfotencheck',
            'HTTP_ORIGIN' => 'https://tierarztportal.com',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
        ])->get('/f/'.$funnel->public_token.'?utm_source=newsletter&utm_medium=email&utm_campaign=herbst&utm_term=hund&utm_content=variante_b')
            ->assertOk();

        $session = PublicSession::query()->sole();

        $this->assertSame('newsletter', $session->utm_source);
        $this->assertSame('email', $session->utm_medium);
        $this->assertSame('herbst', $session->utm_campaign);
        $this->assertSame('hund', $session->utm_term);
        $this->assertSame('variante_b', $session->utm_content);
        $this->assertSame('https://tierarztportal.com/pfotencheck', $session->referrer);
        $this->assertSame('https://tierarztportal.com', $session->embed_origin);
        $this->assertStringStartsWith('Mozilla/5.0 (iPhone', (string) $session->user_agent);

        // Ein Reload ohne Kampagnenparameter darf die Quelle nicht loeschen.
        $this->withCookie('funnel_session_'.$funnel->public_token, $session->token)
            ->get('/f/'.$funnel->public_token)
            ->assertOk();

        $this->assertSame(1, PublicSession::query()->count());
        $this->assertSame('newsletter', $session->refresh()->utm_source);
    }

    public function test_no_column_and_no_log_line_contains_the_raw_ip_address(): void
    {
        $funnel = $this->publishedFunnel();

        // Jede Logzeile mitschneiden -- auch die des PendingSubmissionReceiver,
        // der die Einreichung protokolliert.
        $logLines = [];
        Log::listen(static function (MessageLogged $message) use (&$logLines): void {
            $logLines[] = $message->message.' '.json_encode($message->context);
        });

        $this->withServerVariables(['REMOTE_ADDR' => self::IP_ADDRESS])
            ->get('/f/'.$funnel->public_token)
            ->assertOk();

        $session = PublicSession::query()->sole();

        // Die Strecke bis zum Absenden gehen, damit auch die Uebergabe an
        // FB-031 protokolliert wird.
        Livewire::withCookie('funnel_session_'.$funnel->public_token, $session->token)
            ->test(FunnelRunner::class, ['token' => $funnel->public_token])
            ->set('answers.email', 'anna@example.com')
            ->call('submitStep')
            // Ergebnis-Screen quittieren; danach laeuft die Uebergabe.
            ->call('continueAfterResult')
            ->assertSet('phase', 'done');

        // Gespeichert ist ausschliesslich der gesalzene Hash -- und zwar
        // derselbe, den auch das Audit-Log bildet.
        $this->assertSame(app(IpHasher::class)->hash(self::IP_ADDRESS), $session->ip_hash);
        $this->assertSame(64, strlen((string) $session->ip_hash));

        // Keine Spalte der Sitzung traegt die Adresse.
        $row = (array) DB::table('public_sessions')->where('id', $session->id)->first();

        $this->assertStringNotContainsString(
            self::IP_ADDRESS,
            (string) json_encode($row),
            'Die Roh-IP darf in keiner Spalte der Sitzung stehen.',
        );

        // Und auch keine Logzeile.
        $this->assertNotEmpty($logLines, 'Ohne mitgeschnittene Logzeilen sagt der Test nichts aus.');

        foreach ($logLines as $line) {
            $this->assertStringNotContainsString(
                self::IP_ADDRESS,
                $line,
                'Die Roh-IP darf in keiner Logzeile stehen.',
            );
        }
    }
}
