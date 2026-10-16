<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\AuditAction;
use App\Constants\CallerIdStatus;
use App\Constants\LeadContactStatus;
use App\Constants\LeadState;
use App\Constants\PhoneVerificationChannel;
use App\Constants\TenantType;
use App\Dto\CallerIdValidation;
use App\Filament\Dashboard\Pages\CallerId as CallerIdPage;
use App\Http\Middleware\VerifyTwilioSignature;
use App\Models\AuditLog;
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
use App\Services\Twilio\CallerIdValidationFailed;
use App\Services\Twilio\CallerIdValidationThrottled;
use App\Services\Twilio\OutboundCallClient;
use App\Support\PortalCallerIdLock;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
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

    /**
     * Die Twilio-Anbindung als Doppel -- umschaltbar, siehe setUp().
     *
     * @var CallerIdValidationClient&object{deletionFails: bool, deleted: list<string>}
     */
    private CallerIdValidationClient $twilio;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('twilio.account_sid', 'ACtest');
        config()->set('twilio.auth_token', self::TOKEN);
        config()->set('services.twilio.sid', 'ACtest');
        config()->set('services.twilio.token', self::TOKEN);

        $this->twilio = new class implements CallerIdValidationClient
        {
            /** Laesst die Loeschung bei Twilio scheitern. */
            public bool $deletionFails = false;

            /** @var list<string> Nummern, die bei Twilio geloescht wurden. */
            public array $deleted = [];

            public function requestValidation(string $phoneNumber, string $friendlyName, string $statusCallbackUrl): CallerIdValidation
            {
                return new CallerIdValidation('CAtest', '123456');
            }

            public function deleteValidatedNumber(string $phoneNumber): int
            {
                if ($this->deletionFails) {
                    throw new CallerIdValidationFailed('Twilio ist nicht erreichbar.');
                }

                $this->deleted[] = $phoneNumber;

                return 1;
            }
        };

        $this->app->bind(CallerIdValidationClient::class, fn (): CallerIdValidationClient => $this->twilio);
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
     * Ticket #1: Der Gegenweg zur Bestaetigung. Geprueft wird beides in einem
     * Zug -- die Zeile ist weg und der Haken am Nutzer ist weg. Bliebe eines
     * von beiden stehen, haetten wir entweder einen Nutzer ohne nutzbare
     * Nummer hinter offener Tuer oder eine Zeile ohne Nachweis.
     */
    public function test_loeschen_entfernt_die_nummer_und_die_telefonbestaetigung(): void
    {
        [$tenant, $user] = $this->verifiedUser();
        $callerId = app(CallerIdService::class)->forUser($user);

        $release = app(CallerIdService::class)->releaseNumber($user);

        $this->assertTrue($release->released);
        $this->assertSame('+491721234567', $release->phoneNumber);
        $this->assertNull($release->providerError);
        $this->assertDatabaseMissing('caller_ids', ['id' => $callerId->getKey()]);

        $user = $user->fresh();

        $this->assertNull($user->phone_number_verified_at);
        $this->assertNull($user->phone_e164);
        $this->assertNull($user->phone_verification_channel);
        $this->assertFalse(app(OnboardingService::class)->isPhoneVerified($user));
        $this->assertFalse(PortalCallerIdLock::hasVerifiedNumber($user));
        $this->assertNotNull($tenant->fresh());
    }

    /**
     * Ticket #1: Die Nummer muss auch bei Twilio verschwinden. Bleibt sie dort
     * als Verified Caller ID stehen, kennen wir sie nicht mehr, Twilio laesst
     * sie aber weiter als Rufnummernanzeige zu -- das Auseinanderlaufen aus
     * #96/#97.
     */
    public function test_loeschen_nimmt_den_eintrag_bei_twilio_mit(): void
    {
        [, $user] = $this->verifiedUser();

        $release = app(CallerIdService::class)->releaseNumber($user);

        $this->assertSame(['+491721234567'], $this->twilio->deleted);
        $this->assertSame(1, $release->deletedAtProvider);
    }

    /**
     * Ticket #1: Scheitert die Loeschung bei Twilio, geht die hiesige trotzdem
     * durch -- sonst koennte ein Nutzer seine Nummer wegen eines fremden
     * Ausfalls nicht wechseln. Der Fehlschlag darf aber nicht verschwinden:
     * Die Nummer steht dort weiter und muss von Hand nachgezogen werden.
     */
    public function test_scheitert_die_loeschung_bei_twilio_geht_sie_hier_durch(): void
    {
        [, $user] = $this->verifiedUser();
        $this->twilio->deletionFails = true;

        $release = app(CallerIdService::class)->releaseNumber($user);

        $this->assertTrue($release->released);
        $this->assertSame(0, $release->deletedAtProvider);
        $this->assertSame('Twilio ist nicht erreichbar.', $release->providerError);
        $this->assertNull($user->fresh()->phone_number_verified_at);
        $this->assertDatabaseMissing('caller_ids', ['user_id' => $user->getKey()]);

        $eintrag = AuditLog::query()
            ->where('action', AuditAction::CALLER_ID_RELEASED)
            ->where('user_id', $user->getKey())
            ->sole();

        $this->assertSame('Twilio ist nicht erreichbar.', $eintrag->payload['provider_error']);
        $this->assertSame('+491721234567', $eintrag->payload['phone_number']);
    }

    /**
     * Ticket #1: Nach dem Loeschen steht der Nutzer wieder vor der
     * Telefonsperre. Das ist die eigentliche Folge des Loeschens -- ohne
     * bestaetigte Nummer kein Portal.
     */
    public function test_nach_dem_loeschen_greift_die_telefonsperre_wieder(): void
    {
        [$tenant, $user] = $this->verifiedUser();

        $this->withExceptionHandling();

        // Vorher laeuft die Anfrage an der Sperre vorbei. Ob die Seite danach
        // etwas anderes verlangt (Freigabe des Marktplatzes etwa), ist hier
        // nicht die Frage -- gefragt ist, dass nicht die Telefonsperre umleitet.
        $vorher = $this->actingAs($user)->get(route('portal.overview', ['tenant' => $tenant->uuid]));

        $this->assertNotSame(route('onboarding.phone'), $vorher->headers->get('Location'));

        app(CallerIdService::class)->releaseNumber($user);

        $this->actingAs($user)
            ->get(route('portal.overview', ['tenant' => $tenant->uuid]))
            ->assertRedirect(route('onboarding.phone'));
    }

    /**
     * Ticket #1: Danach muss der Weg von vorn offen sein -- mit einer anderen
     * Nummer, und mit einem echten Validation Request bei Twilio. Eine neue
     * Bestaetigung ohne Vorgang waere der Fehler aus #96.
     */
    public function test_nach_dem_loeschen_ist_eine_neue_bestaetigung_moeglich(): void
    {
        [$tenant, $user] = $this->verifiedUser();

        app(CallerIdService::class)->releaseNumber($user);

        // Die Wartezeit von 60 Sekunden gilt weiter (siehe eigener Test); hier
        // geht es um den Weg danach.
        $this->travel(61)->seconds();

        $neue = $this->requestFor($tenant, $user, '030 7654321');

        $this->assertSame(CallerIdStatus::PENDING, $neue->status);
        $this->assertSame('+49307654321', $neue->phone_number);
        $this->assertSame('CAtest', $neue->validation_sid);

        $this->postSigned([
            'To' => $neue->phone_number,
            'VerificationStatus' => 'success',
            'CallStatus' => 'completed',
        ])->assertNoContent();

        $this->assertSame(CallerIdStatus::VERIFIED, $neue->fresh()->status);
        $this->assertSame('+49307654321', $user->fresh()->phone_e164);
        $this->assertNotNull($user->fresh()->phone_number_verified_at);
    }

    /**
     * Ticket #1: Das Limit haengt am Nutzer und an der Nummer, nicht an der
     * Zeile. Sonst waere Loeschen und Neuanlegen der bequeme Weg am
     * Missbrauchsschutz aus #97 vorbei -- und wir die Anrufmaschine.
     */
    public function test_rate_limit_ueberlebt_das_loeschen(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->phoneUnverified()->create();
        $tenant->users()->attach($user);

        $erlaubt = (int) config('funnel.call.caller_id.limits.requests_per_user');
        $wartezeit = (int) config('funnel.call.caller_id.limits.resend_after_seconds');

        for ($i = 0; $i < $erlaubt; $i++) {
            $this->requestFor($tenant, $user, '0172 1234567');
            $this->travel($wartezeit + 1)->seconds();
        }

        app(CallerIdService::class)->releaseNumber($user);
        $this->travel($wartezeit + 1)->seconds();

        $this->expectException(CallerIdValidationThrottled::class);

        $this->requestFor($tenant, $user, '030 7654321');
    }

    /**
     * Ticket #1: Auch die Wartezeit von 60 Sekunden ueberlebt das Loeschen.
     * Sie haengt sonst am Zeitstempel der Zeile -- und die ist danach weg.
     */
    public function test_wartezeit_ueberlebt_das_loeschen(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->phoneUnverified()->create();
        $tenant->users()->attach($user);

        $this->requestFor($tenant, $user, '0172 1234567');

        app(CallerIdService::class)->releaseNumber($user);

        $this->expectException(CallerIdValidationThrottled::class);

        $this->requestFor($tenant, $user, '030 7654321');
    }

    /**
     * Ticket #1: Mandantentrennung. Der Dienst liest ausschliesslich die Zeile
     * des uebergebenen Nutzers -- ein Kollege im selben Workspace bleibt
     * unberuehrt, samt seiner Bestaetigung.
     */
    public function test_loeschen_trifft_nur_die_eigene_nummer(): void
    {
        [$tenant, $user] = $this->verifiedUser();

        $kollege = User::factory()->phoneUnverified()->create();
        $tenant->users()->attach($kollege);
        $kollegeNummer = $this->requestFor($tenant, $kollege, '030 7654321');

        $this->postSigned([
            'To' => $kollegeNummer->phone_number,
            'VerificationStatus' => 'success',
            'CallStatus' => 'completed',
        ])->assertNoContent();

        app(CallerIdService::class)->releaseNumber($user);

        $this->assertDatabaseHas('caller_ids', [
            'id' => $kollegeNummer->getKey(),
            'status' => CallerIdStatus::VERIFIED->value,
        ]);
        $this->assertNotNull($kollege->fresh()->phone_number_verified_at);
        $this->assertSame('+49307654321', $kollege->fresh()->phone_e164);
        $this->assertSame(['+491721234567'], $this->twilio->deleted);
    }

    /**
     * Ticket #1: Die Ansage vor dem Loeschen. Gezaehlt werden die eigenen
     * Kaeufe mit laufender Frist -- nicht die eines Kollegen, nicht die
     * abgeschlossenen und nicht die, deren Frist schon abgelaufen ist.
     */
    public function test_offene_leads_mit_laufender_frist_werden_gezaehlt(): void
    {
        [$tenant, $user] = $this->verifiedUser();
        $kollege = User::factory()->create();
        $tenant->users()->attach($kollege);

        $this->purchaseFor($tenant, $user, LeadContactStatus::OPEN, now()->addDay());
        $this->purchaseFor($tenant, $user, LeadContactStatus::OPEN, now()->addHours(2));

        // Zaehlen nicht: abgeschlossen, Frist abgelaufen, Kauf eines Kollegen.
        $this->purchaseFor($tenant, $user, LeadContactStatus::BILLABLE, now()->addDay());
        $this->purchaseFor($tenant, $user, LeadContactStatus::OPEN, now()->subHour());
        $this->purchaseFor($tenant, $kollege, LeadContactStatus::OPEN, now()->addDay());

        $this->assertSame(2, app(CallerIdService::class)->countOpenLeadsAtRisk($user));
    }

    /**
     * Ticket #2: Das Datum zur Zahl. Genommen wird die naechste Frist unter
     * genau denselben Leads, die auch gezaehlt werden -- der abgelaufene Lead
     * und der des Kollegen duerfen das Datum nicht nach vorn ziehen.
     */
    public function test_naechste_laufende_frist_gehoert_zur_gezaehlten_menge(): void
    {
        [$tenant, $user] = $this->verifiedUser();
        $kollege = User::factory()->create();
        $tenant->users()->attach($kollege);

        $naechste = now()->addHours(5)->startOfSecond();

        $this->purchaseFor($tenant, $user, LeadContactStatus::OPEN, now()->addDays(3));
        $this->purchaseFor($tenant, $user, LeadContactStatus::OPEN, $naechste);

        // Frueher, aber nicht betroffen: abgelaufen, abgeschlossen, fremd.
        $this->purchaseFor($tenant, $user, LeadContactStatus::OPEN, now()->subHour());
        $this->purchaseFor($tenant, $user, LeadContactStatus::BILLABLE, now()->addHour());
        $this->purchaseFor($tenant, $kollege, LeadContactStatus::OPEN, now()->addMinutes(30));

        $this->assertTrue(
            $naechste->equalTo(app(CallerIdService::class)->nextOpenLeadDeadlineAtRisk($user)),
            'Die genannte Frist gehoert nicht zu den gezaehlten Leads.',
        );
    }

    /** Ohne betroffenen Lead gibt es kein Datum zu nennen. */
    public function test_ohne_betroffene_leads_gibt_es_keine_frist(): void
    {
        [, $user] = $this->verifiedUser();

        $this->assertSame(0, app(CallerIdService::class)->countOpenLeadsAtRisk($user));
        $this->assertNull(app(CallerIdService::class)->nextOpenLeadDeadlineAtRisk($user));
    }

    /**
     * Ein Nutzer ohne Nummer: Der Weg sagt ehrlich, dass es nichts
     * zurueckzunehmen gab, statt einen Vorgang zu protokollieren, den es nicht
     * gab.
     */
    public function test_ohne_nummer_gibt_es_nichts_zurueckzunehmen(): void
    {
        $user = User::factory()->phoneUnverified()->create();

        $release = app(CallerIdService::class)->releaseNumber($user);

        $this->assertFalse($release->released);
        $this->assertSame([], $this->twilio->deleted);
        $this->assertSame(
            0,
            AuditLog::query()->where('action', AuditAction::CALLER_ID_RELEASED)->count(),
        );
    }

    /**
     * Ein Nutzer mit bestaetigter, ueber den Anruf-Weg erworbener Nummer.
     *
     * Bewusst ueber requestFor() und den signierten Rueckruf, nicht per
     * Factory: So steht der Nachweis genauso in der Datenbank, wie er im
     * Betrieb entsteht -- Zeile mit validation_sid, Haken am Nutzer, Kanal
     * `call`.
     *
     * @return array{0: Tenant, 1: User}
     */
    private function verifiedUser(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->phoneUnverified()->create();
        $tenant->users()->attach($user);

        $callerId = $this->requestFor($tenant, $user, '0172 1234567');

        $this->postSigned([
            'To' => $callerId->phone_number,
            'VerificationStatus' => 'success',
            'CallStatus' => 'completed',
        ])->assertNoContent();

        return [$tenant, $user->fresh()];
    }

    /** Ein abgeschlossener Kauf dieses Nutzers samt Stand und Frist des Leads. */
    private function purchaseFor(
        Tenant $tenant,
        User $user,
        LeadContactStatus $contactStatus,
        Carbon $deadline,
    ): LeadPurchase {
        $lead = Lead::factory()->create([
            'contact_status' => $contactStatus,
            'deadline_at' => $deadline,
            'delivered_at' => now()->subDay(),
        ]);

        return LeadPurchase::factory()->create([
            'lead_id' => $lead->getKey(),
            'buyer_tenant_id' => $tenant->getKey(),
            'purchased_by_user_id' => $user->getKey(),
        ]);
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
