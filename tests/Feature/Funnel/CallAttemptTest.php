<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\CallAttemptOutcome;
use App\Constants\CallAttemptStatus;
use App\Constants\CallerIdStatus;
use App\Constants\LeadState;
use App\Constants\TenantType;
use App\Http\Middleware\VerifyTwilioSignature;
use App\Models\BuyerRegistration;
use App\Models\CallAttempt;
use App\Models\CallerId;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CallNotPossible;
use App\Services\CallService;
use App\Services\Twilio\OutboundCallClient;
use Filament\Facades\Filament;
use Illuminate\Testing\TestResponse;
use Tests\Feature\FeatureTest;
use Twilio\Security\RequestValidator;

/**
 * FB-081/FB-082: Anruf beim gekauften Lead und das Protokoll der Versuche.
 *
 * Geprueft wird, was Geld oder Kontaktdaten bewegt: dass die Rueckrufadressen
 * ohne gueltige Twilio-Signatur nichts bewirken -- ueber sie kommt die
 * Gespraechsdauer herein, die spaeter (FB-083) ueber die Abrechnung
 * entscheidet --, dass ohne bestaetigte Rufnummer nicht gewaehlt wird, und dass
 * ein Workspace die Versuche eines anderen nicht sieht.
 */
class CallAttemptTest extends FeatureTest
{
    private const TOKEN = 'test-auth-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('twilio.account_sid', 'ACtest');
        config()->set('twilio.auth_token', self::TOKEN);
        config()->set('services.twilio.sid', 'ACtest');
        config()->set('services.twilio.token', self::TOKEN);
        config()->set('services.twilio.from', '+4930111111');

        $this->app->bind(OutboundCallClient::class, fn (): OutboundCallClient => new class implements OutboundCallClient
        {
            public function call(string $to, string $from, string $answerUrl, string $statusCallbackUrl): string
            {
                return 'CA'.uniqid();
            }

            public function hangUp(string $sid): void {}
        });
    }

    public function test_anruf_legt_einen_versuch_an(): void
    {
        [$purchase, $user, $buyer] = $this->purchase();
        $this->verifiedCallerId($buyer, $user, '+4930222222');

        $attempt = app(CallService::class)->start($purchase, $user, $buyer);

        $this->assertSame('+4930222222', $attempt->caller_number);
        $this->assertSame($purchase->lead->phone_e164, $attempt->lead_number);
        $this->assertSame(CallAttemptStatus::QUEUED, $attempt->status);
        $this->assertStringStartsWith('CA', (string) $attempt->fresh()->provider_call_sid);
    }

    public function test_ohne_bestaetigte_rufnummer_wird_nicht_gewaehlt(): void
    {
        [$purchase, $user, $buyer] = $this->purchase();

        CallerId::factory()->create([
            'tenant_id' => $buyer->getKey(),
            'user_id' => $user->getKey(),
            'status' => CallerIdStatus::PENDING,
        ]);

        $this->expectException(CallNotPossible::class);

        app(CallService::class)->start($purchase, $user, $buyer);
    }

    public function test_kauf_eines_fremden_workspace_laesst_sich_nicht_anrufen(): void
    {
        [$purchase] = $this->purchase();
        [, $fremderNutzer, $fremderWorkspace] = $this->purchase();

        $this->verifiedCallerId($fremderWorkspace, $fremderNutzer, '+4930333333');

        $this->expectException(CallNotPossible::class);

        app(CallService::class)->start($purchase, $fremderNutzer, $fremderWorkspace);
    }

    public function test_bridge_stellt_mit_der_mitarbeiternummer_durch(): void
    {
        $attempt = $this->attempt();

        $twiml = (string) app(CallService::class)->bridgeInstruction($attempt);

        $this->assertStringContainsString($attempt->lead->phone_e164, $twiml);
        // Beim Lead erscheint die bestaetigte Nummer des Mitarbeiters, nicht
        // die Portal-Nummer. Entschieden am 11.09.2026, hebt Entscheidung 1
        // aus Ticket #7 auf.
        $this->assertStringContainsString('callerId="'.$attempt->caller_number.'"', $twiml);
        $this->assertStringNotContainsString('callerId="+4930111111"', $twiml);
    }

    public function test_anweisung_wird_ohne_signatur_nicht_ausgeliefert(): void
    {
        $this->withExceptionHandling();

        $attempt = $this->attempt();

        $this->post(route('twilio.calls.bridge', ['attempt' => $attempt->uuid]))
            ->assertForbidden();
    }

    public function test_signierte_standmeldung_haelt_dauer_und_stand_fest(): void
    {
        $attempt = $this->attempt();

        $this->postSigned(route('twilio.calls.dial-done', ['attempt' => $attempt->uuid]), [
            'DialCallStatus' => 'completed',
            'DialCallDuration' => '42',
            'DialCallSid' => 'CAdial',
        ])->assertOk()->assertSee('<Hangup', escape: false);

        $attempt = $attempt->fresh();

        $this->assertSame(CallAttemptStatus::COMPLETED, $attempt->status);
        $this->assertSame(42, $attempt->duration_seconds);
        $this->assertSame('CAdial', $attempt->provider_dial_sid);
        $this->assertNotNull($attempt->ended_at);
        $this->assertSame('completed', $attempt->provider_payload['DialCallStatus'] ?? null);
    }

    public function test_standmeldung_ohne_signatur_bleibt_folgenlos(): void
    {
        $this->withExceptionHandling();

        $attempt = $this->attempt();

        $this->post(route('twilio.calls.dial-done', ['attempt' => $attempt->uuid]), [
            'DialCallStatus' => 'completed',
            'DialCallDuration' => '600',
        ])->assertForbidden();

        $attempt = $attempt->fresh();

        $this->assertSame(CallAttemptStatus::QUEUED, $attempt->status);
        $this->assertNull($attempt->duration_seconds);
    }

    public function test_verspaetete_meldung_aendert_ein_ergebnis_nicht_mehr(): void
    {
        $attempt = $this->attempt();

        $url = route('twilio.calls.dial-done', ['attempt' => $attempt->uuid]);

        $this->postSigned($url, ['DialCallStatus' => 'no-answer'])->assertOk();
        $this->postSigned($url, ['DialCallStatus' => 'completed', 'DialCallDuration' => '99'])->assertOk();

        $attempt = $attempt->fresh();

        $this->assertSame(CallAttemptStatus::NO_ANSWER, $attempt->status);
        $this->assertNull($attempt->duration_seconds);
    }

    public function test_kaeufer_nimmt_nicht_ab_und_der_versuch_zaehlt_nicht(): void
    {
        $attempt = $this->attempt();

        $this->postSigned(route('twilio.calls.status', ['attempt' => $attempt->uuid]), [
            'CallStatus' => 'no-answer',
        ])->assertNoContent();

        $attempt = $attempt->fresh();

        $this->assertSame(CallAttemptOutcome::FAILED_IGNORED, $attempt->outcome);
        $this->assertSame(CallService::IGNORE_BUYER_NO_ANSWER, $attempt->ignore_reason);
        $this->assertNotNull($attempt->ended_at);
    }

    public function test_lead_bein_haelt_abnehmen_und_auflegen_fest(): void
    {
        $attempt = $this->attempt();

        $url = route('twilio.calls.leg-status', ['attempt' => $attempt->uuid]);

        $this->postSigned($url, ['CallStatus' => 'answered', 'CallSid' => 'CAlead'])->assertNoContent();

        $answeredAt = $attempt->fresh()->answered_at;

        $this->assertNotNull($answeredAt);
        $this->assertSame('CAlead', $attempt->fresh()->provider_dial_sid);

        // Zweite Zustellung derselben Meldung veraendert nichts.
        $this->postSigned($url, ['CallStatus' => 'answered', 'CallSid' => 'CAanders'])->assertNoContent();

        $this->assertTrue($answeredAt->equalTo($attempt->fresh()->answered_at));
        $this->assertSame('CAlead', $attempt->fresh()->provider_dial_sid);

        $this->postSigned($url, ['CallStatus' => 'completed', 'CallSid' => 'CAlead'])->assertNoContent();

        $this->assertNotNull($attempt->fresh()->ended_at);
    }

    public function test_anrufbeantworter_erkennung_wird_mitgeschrieben(): void
    {
        $attempt = $this->attempt();

        $this->postSigned(route('twilio.calls.machine-detection', ['attempt' => $attempt->uuid]), [
            'AnsweredBy' => 'machine_end_beep',
        ])->assertNoContent();

        $this->assertSame('machine_end_beep', $attempt->fresh()->answered_by);
        // Gedeutet wird sie nicht: Der Stand bleibt, wie er war.
        $this->assertSame(CallAttemptStatus::QUEUED, $attempt->fresh()->status);
    }

    public function test_ein_workspace_sieht_die_versuche_eines_anderen_nicht(): void
    {
        $eigener = $this->attempt();
        $fremder = $this->attempt();

        Filament::setTenant($eigener->tenant, isQuiet: true);

        $sichtbar = CallAttempt::query()->pluck('id')->all();

        Filament::setTenant(null, isQuiet: true);

        $this->assertSame([$eigener->getKey()], $sichtbar);
        $this->assertNotContains($fremder->getKey(), $sichtbar);
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function postSigned(string $url, array $payload): TestResponse
    {
        $signature = (new RequestValidator(self::TOKEN))->computeSignature($url, $payload);

        return $this->withHeader(VerifyTwilioSignature::HEADER, $signature)->post($url, $payload);
    }

    private function attempt(): CallAttempt
    {
        [$purchase, $user, $buyer] = $this->purchase();
        $this->verifiedCallerId($buyer, $user, '+4930'.fake()->numerify('#######'));

        return app(CallService::class)->start($purchase, $user, $buyer);
    }

    private function verifiedCallerId(Tenant $tenant, User $user, string $number): CallerId
    {
        return CallerId::factory()->verified()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'phone_number' => $number,
        ]);
    }

    /**
     * @return array{LeadPurchase, User, Tenant}
     */
    private function purchase(): array
    {
        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $buyer = BuyerRegistration::factory()->approved()->create()->tenant->fresh();

        $user = User::factory()->create();
        $buyer->users()->attach($user);

        $lead = Lead::factory()->inState(LeadState::VERKAUFT)->create([
            'tenant_id' => $operator->getKey(),
            'phone_e164' => '+4989'.fake()->numerify('#######'),
        ]);

        $purchase = LeadPurchase::factory()->create([
            'lead_id' => $lead->getKey(),
            'buyer_tenant_id' => $buyer->getKey(),
        ]);

        return [$purchase->fresh(), $user, $buyer];
    }
}
