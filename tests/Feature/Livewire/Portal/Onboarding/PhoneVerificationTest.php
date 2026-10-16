<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Portal\Onboarding;

use App\Constants\CallerIdStatus;
use App\Dto\CallerIdValidation;
use App\Livewire\Portal\Onboarding\PhoneVerification;
use App\Models\CallerId;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Twilio\CallerIdValidationClient;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\FeatureTest;

/**
 * Die Oberflaeche der Rufnummernbestaetigung (Ticket #98).
 *
 * Geprueft werden die drei Zustaende, die ein Kaeufer sieht -- Nummer eingeben,
 * auf den Anruf warten, fertig --, die Fehlerstaende des Anrufs und die
 * Abwesenheit des SMS-Wegs.
 *
 * Der letzte Test ist der eigentliche Grund fuer diese Datei: Der Code kommt
 * seit Ticket #97 nicht mehr per SMS, sondern steht auf dem Bildschirm und
 * wird am Telefon eingetippt. Eine Oberflaeche, die weiter von einer SMS
 * spricht, schickt den Nutzer auf sein Telefon, um dort auf etwas zu warten,
 * das nie kommt -- und das faellt in einem Rendertest auf, der die Woerter
 * selbst sucht.
 */
class PhoneVerificationTest extends FeatureTest
{
    /** Der angesagte Code der Attrappe -- er muss gross auf dem Schirm stehen. */
    public const CODE = '483921';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(CallerIdValidationClient::class, fn (): CallerIdValidationClient => new class implements CallerIdValidationClient
        {
            public function requestValidation(string $phoneNumber, string $friendlyName, string $statusCallbackUrl): CallerIdValidation
            {
                return new CallerIdValidation('CAtest', PhoneVerificationTest::CODE);
            }
        });
    }

    public function test_der_erste_zustand_fragt_nach_der_nummer(): void
    {
        $this->actingAs($this->buyer());

        Livewire::test(PhoneVerification::class)
            ->assertSet('state', PhoneVerification::STATE_NUMBER)
            ->assertSee(__('portal.onboarding.phone.number.heading'))
            ->assertSee(__('portal.onboarding.phone.number.field'))
            ->assertSee(__('portal.onboarding.phone.number.submit'))
            // Kein Code-Feld: Eingetippt wird am Telefon.
            ->assertDontSee(__('portal.onboarding.phone.call.code_label'));
    }

    public function test_eine_leere_nummer_fuehrt_nicht_zum_anruf(): void
    {
        $this->actingAs($this->buyer());

        Livewire::test(PhoneVerification::class)
            ->call('send')
            ->assertSet('state', PhoneVerification::STATE_NUMBER)
            ->assertSee(__('portal.onboarding.phone.errors.required'));

        $this->assertDatabaseCount('caller_ids', 0);
    }

    public function test_der_angeforderte_anruf_zeigt_den_code_und_die_ansage(): void
    {
        $this->actingAs($this->buyer());

        Livewire::test(PhoneVerification::class)
            ->set('phone', '172 1234567')
            ->call('send')
            ->assertSet('state', PhoneVerification::STATE_CALLING)
            ->assertSee(__('portal.onboarding.phone.call.heading'))
            ->assertSee(self::CODE)
            ->assertSee(__('portal.onboarding.phone.call.code_label'))
            ->assertSee(__('portal.onboarding.phone.call.code_hint'))
            ->assertSee(__('portal.onboarding.phone.call.waiting'))
            ->assertSee(__('portal.onboarding.phone.call.validity', [
                'minutes' => (int) config('funnel.call.caller_id.validation_ttl_minutes'),
            ]))
            // Der Ausweg nach Ablauf der Frist: Umgeschaltet wird im Browser,
            // ausgeliefert werden beide Fassungen -- sonst braeuchte der
            // Wechsel eine Anfrage.
            ->assertSee(__('portal.onboarding.phone.call.no_call_heading'))
            ->assertSee(__('portal.onboarding.phone.call.retry'))
            ->assertSee(__('portal.onboarding.phone.call.edit'));
    }

    /**
     * Ein offener Anruf aus einer frueheren Sitzung wird aufgenommen: Wer die
     * Seite neu laedt, braucht denselben Code, den die Ansage verlangt.
     */
    public function test_ein_laufender_anruf_wird_beim_laden_aufgenommen(): void
    {
        $user = $this->buyer();
        $this->callerIdFor($user, CallerIdStatus::PENDING);

        $this->actingAs($user);

        Livewire::test(PhoneVerification::class)
            ->assertSet('state', PhoneVerification::STATE_CALLING)
            ->assertSee(self::CODE);
    }

    /**
     * Jeder Fehlerstand aus Ticket #97 braucht einen eigenen, verstaendlichen
     * Satz -- und keinen Dauerzustand "wartet".
     *
     * @return array<string, array{CallerIdStatus}>
     */
    public static function failureStates(): array
    {
        return [
            'nicht angenommen' => [CallerIdStatus::NO_ANSWER],
            'nicht bestaetigt' => [CallerIdStatus::NOT_CONFIRMED],
            'technisch gescheitert' => [CallerIdStatus::FAILED],
        ];
    }

    #[DataProvider('failureStates')]
    public function test_ein_fehlgeschlagener_anruf_nennt_seinen_grund(CallerIdStatus $status): void
    {
        $user = $this->buyer();
        $callerId = $this->callerIdFor($user, CallerIdStatus::PENDING);

        $this->actingAs($user);

        $component = Livewire::test(PhoneVerification::class)
            ->assertSet('state', PhoneVerification::STATE_CALLING);

        // So kommt der Stand im Betrieb an: Twilio meldet ihn an den Rueckruf,
        // die Seite zieht ihn beim Nachfragen nach.
        $callerId->forceFill(['status' => $status, 'validation_code' => null])->save();

        $component->call('refreshStatus')
            ->assertSet('state', PhoneVerification::STATE_NUMBER)
            ->assertSee($status->failureHint())
            ->assertDontSee(__('portal.onboarding.phone.call.waiting'));
    }

    public function test_die_bestaetigte_nummer_fuehrt_weiter(): void
    {
        $user = $this->buyer();
        $callerId = $this->callerIdFor($user, CallerIdStatus::PENDING);

        $this->actingAs($user);

        $component = Livewire::test(PhoneVerification::class);

        $callerId->forceFill([
            'status' => CallerIdStatus::VERIFIED,
            'validation_code' => null,
            'verified_at' => now(),
        ])->save();

        $component->call('refreshStatus')
            ->assertSet('state', PhoneVerification::STATE_SUCCESS)
            ->assertSee(__('portal.onboarding.phone.success.heading'))
            ->assertSee(__('portal.onboarding.phone.success.submit'))
            ->assertDontSee(self::CODE);
    }

    /**
     * Keine Spur des SMS-Wegs mehr in der Oberflaeche -- in keinem Zustand.
     *
     * Gesucht werden die Woerter selbst und die Merkmale, mit denen der
     * Browser einen SMS-Code anbot: `one-time-code` fuer Safaris Autofill und
     * die Kaestchen, an denen WebOTP hing.
     */
    public function test_kein_zustand_spricht_noch_von_einer_sms(): void
    {
        $user = $this->buyer();
        $this->actingAs($user);

        $screens = [
            Livewire::test(PhoneVerification::class),
            Livewire::test(PhoneVerification::class)->set('phone', '172 1234567')->call('send'),
        ];

        foreach ($screens as $screen) {
            foreach (['SMS', 'Kurznachricht', 'Mobilnummer', 'one-time-code', 'code-box', 'data-code-boxes'] as $term) {
                $screen->assertDontSee($term);
            }
        }
    }

    /**
     * Ein Kaeufer-Workspace mit unbestaetigter Nummer: der Fall, in dem diese
     * Seite im Betrieb erscheint.
     */
    private function buyer(): User
    {
        $tenant = Tenant::factory()->buyer()->create();
        $user = User::factory()->phoneUnverified()->create();
        $tenant->users()->attach($user);

        return $user;
    }

    private function callerIdFor(User $user, CallerIdStatus $status): CallerId
    {
        return CallerId::factory()->create([
            'tenant_id' => $user->tenants()->first()->getKey(),
            'user_id' => $user->getKey(),
            'status' => $status,
            'validation_code' => self::CODE,
        ]);
    }
}
