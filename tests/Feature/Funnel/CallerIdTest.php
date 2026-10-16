<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\CallerIdStatus;
use App\Constants\LeadState;
use App\Constants\PhoneVerificationChannel;
use App\Constants\TenantType;
use App\Dto\CallerIdValidation;
use App\Filament\Dashboard\Pages\CallerId as CallerIdPage;
use App\Http\Middleware\VerifyTwilioSignature;
use App\Models\BuyerRegistration;
use App\Models\CallerId;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CallerIdService;
use App\Services\CallService;
use App\Services\Onboarding\OnboardingService;
use App\Services\Twilio\CallerIdValidationClient;
use App\Services\Twilio\CallerIdValidationThrottled;
use App\Services\Twilio\OutboundCallClient;
use Filament\Facades\Filament;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\FeatureTest;
use Twilio\Security\RequestValidator;

/**
 * FB-080: Bestaetigte Rufnummer des Kaeufer-Mitarbeiters.
 *
 * Geprueft wird, was schiefgehen kann, ohne dass es auffaellt: Der Rueckruf von
 * Twilio entscheidet, welche Nummer beim Endkunden erscheint, und die Adresse
 * ist oeffentlich erreichbar. Ohne Signaturpruefung schaltet ein einziger POST
 * eine fremde Nummer frei. Dazu die Mandantentrennung -- ein Workspace darf die
 * Nummern eines anderen nicht sehen.
 */
class CallerIdTest extends FeatureTest
{
    private const TOKEN = 'test-auth-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('twilio.account_sid', 'ACtest');
        config()->set('twilio.auth_token', self::TOKEN);
        config()->set('services.twilio.sid', 'ACtest');
        config()->set('services.twilio.token', self::TOKEN);

        $this->app->bind(CallerIdValidationClient::class, fn (): CallerIdValidationClient => new class implements CallerIdValidationClient
        {
            public function requestValidation(string $phoneNumber, string $friendlyName, string $statusCallbackUrl): CallerIdValidation
            {
                return new CallerIdValidation('CAtest', '123456');
            }
        });
    }

    public function test_signierter_rueckruf_bestaetigt_die_nummer(): void
    {
        $callerId = $this->pendingCallerId();

        $this->postSigned([
            'To' => $callerId->phone_number,
            'VerificationStatus' => 'success',
        ])->assertNoContent();

        $this->assertSame(CallerIdStatus::VERIFIED, $callerId->fresh()->status);
        $this->assertNull($callerId->fresh()->validation_code);
    }

    /**
     * Ticket #97: Der erfolgreiche Bestaetigungsanruf ist seit dem Abbau von
     * Twilio Verify der einzige Weg, auf dem
     * `users.phone_number_verified_at` gesetzt wird -- und damit der Schluessel
     * zum Portal. Beides muss in einem Zug stehen: Eine gruene Caller-ID ohne
     * Haken am Nutzer liesse ihn weiter vor der Telefonsperre stehen, ein Haken
     * ohne Caller-ID waere der Fehler aus #96.
     */
    public function test_erfolgreicher_anruf_hebt_auch_die_telefonsperre(): void
    {
        $user = User::factory()->phoneUnverified()->create();
        $tenant = Tenant::factory()->create();
        $tenant->users()->attach($user);

        $callerId = $this->requestFor($tenant, $user, '0172 1234567');

        $this->assertNull($user->fresh()->phone_number_verified_at);

        $this->postSigned([
            'To' => $callerId->phone_number,
            'VerificationStatus' => 'success',
            'CallStatus' => 'completed',
        ])->assertNoContent();

        $user = $user->fresh();

        $this->assertSame(CallerIdStatus::VERIFIED, $callerId->fresh()->status);
        $this->assertNotNull($user->phone_number_verified_at);
        $this->assertSame('+491721234567', $user->phone_e164);
        $this->assertSame(PhoneVerificationChannel::CALL, $user->phone_verification_channel);
        $this->assertTrue(app(OnboardingService::class)->isPhoneVerified($user));
    }

    /**
     * Ticket #97: Ein gescheiterter Anruf laesst die Sperre stehen. Sonst
     * waere der Haken am Nutzer kein Nachweis mehr.
     */
    public function test_gescheiterter_anruf_hebt_die_telefonsperre_nicht(): void
    {
        $user = User::factory()->phoneUnverified()->create();
        $tenant = Tenant::factory()->create();
        $tenant->users()->attach($user);

        $callerId = $this->requestFor($tenant, $user, '0172 1234567');

        $this->postSigned([
            'To' => $callerId->phone_number,
            'VerificationStatus' => 'failed',
            'CallStatus' => 'completed',
        ])->assertNoContent();

        $this->assertNull($user->fresh()->phone_number_verified_at);
    }

    /**
     * Ticket #97: Eine Nummer, die bereits an einem anderen Konto bestaetigt
     * ist, laesst sich nicht beanspruchen -- auch dann nicht, wenn dieses
     * Konto dazu keine `caller_ids`-Zeile hat. Genau so steht der Bestand da:
     * bestaetigt ueber den abgeloesten SMS-Weg, ohne Validation Request.
     */
    public function test_fremde_bestaetigte_nummer_eines_anderen_kontos_wird_abgewiesen(): void
    {
        User::factory()->phoneVerified('+491721234567')->create();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->phoneUnverified()->create();
        $tenant->users()->attach($user);

        $this->expectException(InvalidArgumentException::class);

        $this->requestFor($tenant, $user, '0172 1234567');
    }

    /**
     * Ticket #97: Nach der Bestaetigung ist die Nummer im Anruf beim Lead
     * tatsaechlich verwendbar -- das ist der Zweck des ganzen Umbaus.
     */
    public function test_nach_der_bestaetigung_waehlt_der_callservice_mit_dieser_nummer(): void
    {
        config()->set('services.twilio.from', '+4930111111');

        $this->app->bind(OutboundCallClient::class, fn (): OutboundCallClient => new class implements OutboundCallClient
        {
            public function call(string $to, string $from, string $answerUrl, string $statusCallbackUrl): string
            {
                return 'CAdial';
            }

            public function hangUp(string $sid): void {}
        });

        $buyer = BuyerRegistration::factory()->approved()->create()->tenant->fresh();
        $user = User::factory()->phoneUnverified()->create();
        $buyer->users()->attach($user);

        $lead = Lead::factory()->inState(LeadState::VERKAUFT)->create([
            'tenant_id' => Tenant::factory()->create(['type' => TenantType::OPERATOR])->getKey(),
            'phone_e164' => '+4989'.fake()->numerify('#######'),
        ]);

        $purchase = LeadPurchase::factory()->create([
            'lead_id' => $lead->getKey(),
            'buyer_tenant_id' => $buyer->getKey(),
        ]);

        $callerId = $this->requestFor($buyer, $user, '030 1234567');

        $this->postSigned([
            'To' => $callerId->phone_number,
            'VerificationStatus' => 'success',
            'CallStatus' => 'completed',
        ])->assertNoContent();

        $attempt = app(CallService::class)->start($purchase->fresh(), $user, $buyer);

        $this->assertSame('+49301234567', $attempt->caller_number);
    }

    public function test_rueckruf_ohne_signatur_wird_abgewiesen(): void
    {
        $this->withExceptionHandling();

        $callerId = $this->pendingCallerId();

        $this->postJson(route('twilio.caller-id.validation-status'), [
            'To' => $callerId->phone_number,
            'VerificationStatus' => 'success',
        ])->assertForbidden();

        $this->assertSame(CallerIdStatus::PENDING, $callerId->fresh()->status);
    }

    public function test_rueckruf_mit_falscher_signatur_wird_abgewiesen(): void
    {
        $this->withExceptionHandling();

        $callerId = $this->pendingCallerId();

        $this->withHeader(VerifyTwilioSignature::HEADER, 'offensichtlich-falsch')
            ->post(route('twilio.caller-id.validation-status'), [
                'To' => $callerId->phone_number,
                'VerificationStatus' => 'success',
            ])->assertForbidden();

        $this->assertSame(CallerIdStatus::PENDING, $callerId->fresh()->status);
    }

    public function test_signatur_eines_fremden_tokens_wird_abgewiesen(): void
    {
        $this->withExceptionHandling();

        $callerId = $this->pendingCallerId();

        $payload = [
            'To' => $callerId->phone_number,
            'VerificationStatus' => 'success',
        ];

        $signature = (new RequestValidator('fremder-token'))->computeSignature(
            route('twilio.caller-id.validation-status'),
            $payload,
        );

        $this->withHeader(VerifyTwilioSignature::HEADER, $signature)
            ->post(route('twilio.caller-id.validation-status'), $payload)
            ->assertForbidden();

        $this->assertSame(CallerIdStatus::PENDING, $callerId->fresh()->status);
    }

    /**
     * Ticket #97: Der Anruf kam zustande, bestaetigt wurde nichts --
     * Anrufbeantworter, falscher Code oder aufgelegt. Twilio trennt die drei
     * nicht, deshalb trennen wir sie auch nicht.
     */
    public function test_gescheiterte_bestaetigung_schaltet_die_nummer_nicht_frei(): void
    {
        $callerId = $this->pendingCallerId();

        $this->postSigned([
            'To' => $callerId->phone_number,
            'VerificationStatus' => 'failed',
            'CallStatus' => 'completed',
        ])->assertNoContent();

        $this->assertSame(CallerIdStatus::NOT_CONFIRMED, $callerId->fresh()->status);
        $this->assertFalse($callerId->fresh()->isUsableAsCallerId());
        $this->assertTrue($callerId->fresh()->status->isFailure());
    }

    /**
     * Ticket #97: Es hat niemand abgenommen. Eigener Stand, weil der Nutzer
     * etwas anderes tun muss als bei einem nicht bestaetigten Anruf -- und
     * weil kein Stand "wartet" stehen bleiben darf, auf das nichts folgt.
     */
    #[DataProvider('unbeantworteteAnrufe')]
    public function test_nicht_angenommener_anruf_bekommt_einen_eigenen_stand(string $callStatus): void
    {
        $callerId = $this->pendingCallerId();

        $this->postSigned([
            'To' => $callerId->phone_number,
            'VerificationStatus' => 'failed',
            'CallStatus' => $callStatus,
        ])->assertNoContent();

        $this->assertSame(CallerIdStatus::NO_ANSWER, $callerId->fresh()->status);
        $this->assertFalse($callerId->fresh()->isUsableAsCallerId());
        $this->assertNull($callerId->fresh()->expires_at);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unbeantworteteAnrufe(): array
    {
        return [
            'niemand nimmt ab' => ['no-answer'],
            'besetzt' => ['busy'],
            'abgebrochen' => ['canceled'],
        ];
    }

    /** Ticket #97: Twilio kam gar nicht durch -- technischer Fehler. */
    public function test_technisch_gescheiterter_anruf_bleibt_fehlgeschlagen(): void
    {
        $callerId = $this->pendingCallerId();

        $this->postSigned([
            'To' => $callerId->phone_number,
            'VerificationStatus' => 'failed',
            'CallStatus' => 'failed',
        ])->assertNoContent();

        $this->assertSame(CallerIdStatus::FAILED, $callerId->fresh()->status);
    }

    /**
     * Ticket #97: Eine Festnetznummer laesst sich bestaetigen -- ein
     * Handwerksbetrieb erreicht seine Leads ueber die Werkstattnummer.
     */
    public function test_festnetznummer_laesst_sich_bestaetigen(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->users()->attach($user);

        $callerId = app(CallerIdService::class)->requestValidation(
            $tenant,
            $user,
            '030 1234567',
            route('twilio.caller-id.validation-status'),
        );

        $this->postSigned([
            'To' => $callerId->phone_number,
            'VerificationStatus' => 'success',
            'CallStatus' => 'completed',
        ])->assertNoContent();

        $this->assertSame(CallerIdStatus::VERIFIED, $callerId->fresh()->status);
        $this->assertTrue($callerId->fresh()->isUsableAsCallerId());
    }

    /**
     * Ticket #97: Zwei Anforderungen hintereinander -- die zweite wartet. Der
     * Bestaetigungsanruf kostet Geld und laesst ein echtes Telefon klingeln.
     */
    public function test_zweite_anforderung_muss_die_wartezeit_abwarten(): void
    {
        config()->set('funnel.call.caller_id.limits.resend_after_seconds', 60);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->users()->attach($user);

        $this->requestFor($tenant, $user, '030 1234567');

        try {
            $this->requestFor($tenant, $user, '030 1234567');
            $this->fail('Die zweite Anforderung haette abgewiesen werden muessen.');
        } catch (CallerIdValidationThrottled $exception) {
            $this->assertGreaterThan(0, $exception->retryAfterSeconds);
        }
    }

    /** Ticket #97: Das Zeitfenster zaehlt je Nutzer. */
    public function test_rate_limit_zaehlt_je_nutzer(): void
    {
        config()->set('funnel.call.caller_id.limits.resend_after_seconds', 0);
        config()->set('funnel.call.caller_id.limits.requests_per_user', 2);
        config()->set('funnel.call.caller_id.limits.requests_per_phone', 99);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->users()->attach($user);

        $this->requestFor($tenant, $user, '030 1234567');
        $this->requestFor($tenant, $user, '030 7654321');

        $this->expectException(CallerIdValidationThrottled::class);

        $this->requestFor($tenant, $user, '030 1111111');
    }

    /**
     * Ticket #97: ... und je Nummer. Sonst liesse sich ein fremder Anschluss
     * ueber mehrere Konten beliebig oft anklingeln.
     */
    public function test_rate_limit_zaehlt_je_nummer_ueber_konten_hinweg(): void
    {
        config()->set('funnel.call.caller_id.limits.resend_after_seconds', 0);
        config()->set('funnel.call.caller_id.limits.requests_per_phone', 1);
        config()->set('funnel.call.caller_id.limits.requests_per_user', 99);

        $tenant = Tenant::factory()->create();
        $erster = User::factory()->create();
        $zweiter = User::factory()->create();
        $tenant->users()->attach($erster);
        $tenant->users()->attach($zweiter);

        $this->requestFor($tenant, $erster, '030 1234567');

        $this->expectException(CallerIdValidationThrottled::class);

        $this->requestFor($tenant, $zweiter, '030 1234567');
    }

    /**
     * Ticket #97: Eine abgewiesene Anforderung darf keine Spur hinterlassen --
     * sonst wartet die Oberflaeche auf einen Anruf, den niemand ausgeloest hat.
     */
    public function test_abgewiesene_anforderung_legt_keine_zeile_an(): void
    {
        config()->set('funnel.call.caller_id.limits.resend_after_seconds', 0);
        config()->set('funnel.call.caller_id.limits.requests_per_user', 1);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->users()->attach($user);

        $this->requestFor($tenant, $user, '030 1234567');

        try {
            $this->requestFor($tenant, $user, '030 7654321');
        } catch (CallerIdValidationThrottled) {
            // erwartet
        }

        $this->assertDatabaseMissing('caller_ids', ['phone_number' => '+49307654321']);
        $this->assertSame(
            '+49301234567',
            app(CallerIdService::class)->forUser($user)?->phone_number,
        );
    }

    private function requestFor(Tenant $tenant, User $user, string $input): CallerId
    {
        return app(CallerIdService::class)->requestValidation(
            $tenant,
            $user,
            $input,
            route('twilio.caller-id.validation-status'),
        );
    }

    public function test_ein_workspace_sieht_die_nummern_eines_anderen_nicht(): void
    {
        $eigene = $this->pendingCallerId();
        $fremde = $this->pendingCallerId();

        Filament::setTenant($eigene->tenant, isQuiet: true);

        $sichtbar = CallerId::query()->pluck('id')->all();

        Filament::setTenant(null, isQuiet: true);

        $this->assertSame([$eigene->getKey()], $sichtbar);
        $this->assertNotContains($fremde->getKey(), $sichtbar);
    }

    public function test_bestaetigte_nummer_eines_kollegen_laesst_sich_nicht_beanspruchen(): void
    {
        $kollege = $this->pendingCallerId();
        $kollege->forceFill(['status' => CallerIdStatus::VERIFIED])->save();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->users()->attach($user);

        $this->expectException(InvalidArgumentException::class);

        app(CallerIdService::class)->requestValidation(
            $tenant,
            $user,
            $kollege->phone_number,
            route('twilio.caller-id.validation-status'),
        );
    }

    public function test_nummer_wird_in_e164_gespeichert(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->users()->attach($user);

        $callerId = app(CallerIdService::class)->requestValidation(
            $tenant,
            $user,
            '030 1234567',
            route('twilio.caller-id.validation-status'),
        );

        $this->assertSame('+49301234567', $callerId->phone_number);
        $this->assertSame('123456', $callerId->validation_code);
        $this->assertSame(CallerIdStatus::PENDING, $callerId->status);
    }

    public function test_seite_fordert_die_bestaetigung_an(): void
    {
        $buyer = BuyerRegistration::factory()->approved()->create()->tenant->fresh();
        $user = User::factory()->create();
        $buyer->users()->attach($user);

        $this->actingAs($user);
        Filament::setTenant($buyer, isQuiet: true);

        Livewire::test(CallerIdPage::class)
            ->fillForm(['phone_number' => '030 7654321'])
            ->call('requestValidation')
            ->assertHasNoFormErrors();

        Filament::setTenant(null, isQuiet: true);

        $this->assertDatabaseHas('caller_ids', [
            'tenant_id' => $buyer->getKey(),
            'user_id' => $user->getKey(),
            'phone_number' => '+49307654321',
            'status' => CallerIdStatus::PENDING->value,
        ]);
    }

    public function test_seite_bleibt_ohne_marktplatz_freigabe_verschlossen(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->users()->attach($user);

        $this->actingAs($user);
        Filament::setTenant($tenant, isQuiet: true);

        $erlaubt = CallerIdPage::canAccess();

        Filament::setTenant(null, isQuiet: true);

        $this->assertFalse($erlaubt);
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function postSigned(array $payload): TestResponse
    {
        $url = route('twilio.caller-id.validation-status');

        $signature = (new RequestValidator(self::TOKEN))->computeSignature($url, $payload);

        return $this->withHeader(VerifyTwilioSignature::HEADER, $signature)->post($url, $payload);
    }

    /**
     * Ticket #96: Die Bestaetigung im Onboarding laeuft ueber Twilio Verify
     * (SMS-Code) und legt im Twilio-Konto keine Verified Caller ID an. Eine so
     * uebernommene Zeile steht bei uns auf `verified`, hat aber keinen
     * Validation Request -- Twilio wuerde den Anruf mit 21210 ablehnen.
     * Deshalb gilt sie nicht als anrufbereit.
     */
    public function test_ohne_validation_request_gilt_die_nummer_nicht_als_anrufbereit(): void
    {
        $callerId = $this->pendingCallerId();

        $callerId->forceFill([
            'status' => CallerIdStatus::VERIFIED,
            'validation_sid' => null,
            'validation_code' => null,
            'verified_at' => now(),
            'expires_at' => null,
        ])->save();

        $this->assertTrue($callerId->fresh()->status->isVerified());
        $this->assertFalse(
            $callerId->fresh()->isUsableAsCallerId(),
            'Eine Nummer ohne Validation Request darf nicht als Anrufabsender gelten.',
        );

        // Mit Vorgang bei Twilio bleibt sie es.
        $callerId->forceFill(['validation_sid' => 'CAtest'])->save();

        $this->assertTrue($callerId->fresh()->isUsableAsCallerId());
    }

    private function pendingCallerId(): CallerId
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->users()->attach($user);

        return CallerId::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
        ]);
    }
}
