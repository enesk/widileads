<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\CallerIdStatus;
use App\Dto\CallerIdValidation;
use App\Filament\Dashboard\Pages\CallerId as CallerIdPage;
use App\Http\Middleware\VerifyTwilioSignature;
use App\Models\BuyerRegistration;
use App\Models\CallerId;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CallerIdService;
use App\Services\Twilio\CallerIdValidationClient;
use Filament\Facades\Filament;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Livewire\Livewire;
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

    public function test_gescheiterte_bestaetigung_schaltet_die_nummer_nicht_frei(): void
    {
        $callerId = $this->pendingCallerId();

        $this->postSigned([
            'To' => $callerId->phone_number,
            'VerificationStatus' => 'failed',
        ])->assertNoContent();

        $this->assertSame(CallerIdStatus::FAILED, $callerId->fresh()->status);
        $this->assertFalse($callerId->fresh()->isUsableAsCallerId());
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
