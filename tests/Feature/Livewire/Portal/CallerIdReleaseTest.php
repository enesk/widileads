<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Portal;

use App\Constants\LeadContactStatus;
use App\Dto\CallerIdValidation;
use App\Livewire\Portal\CallerIdVerification;
use App\Models\CallerId;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Twilio\CallerIdValidationClient;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * Die Rufnummer selbst zuruecknehmen (Ticket #2).
 *
 * Geprueft wird die Rueckfrage und das, was danach steht. Die Rueckfrage ist
 * der eigentliche Gegenstand: Sie nennt die Folgen -- kein Anruf mehr, nur
 * noch die Bestaetigungsseite, laufende Fristen -- und nicht "Sind Sie
 * sicher?". Ein Test, der nur das Loeschen prueft, liesse genau den Teil
 * offen, der dem Nutzer die Entscheidung erlaubt.
 */
class CallerIdReleaseTest extends FeatureTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // Twilio wird in diesem Test nicht gefragt: Was dort geloescht wird,
        // gehoert zum Dienst aus Ticket #1 und ist dort geprueft.
        $this->app->bind(CallerIdValidationClient::class, fn (): CallerIdValidationClient => new class implements CallerIdValidationClient
        {
            public function requestValidation(string $phoneNumber, string $friendlyName, string $statusCallbackUrl): CallerIdValidation
            {
                return new CallerIdValidation('CAtest', '483921');
            }

            public function deleteValidatedNumber(string $phoneNumber): int
            {
                return 1;
            }
        });
    }

    public function test_die_bestaetigte_nummer_traegt_einen_weg_zum_aendern(): void
    {
        $this->actingAsBuyerWithNumber();

        Livewire::test(CallerIdVerification::class)
            ->assertSee(__('call.caller_id.portal.release.action'));
    }

    public function test_die_rueckfrage_nennt_die_folgen_auch_ohne_offene_leads(): void
    {
        $this->actingAsBuyerWithNumber();

        Livewire::test(CallerIdVerification::class)
            ->call('confirmRelease')
            ->assertSet('confirmingRelease', true)
            ->assertSee(__('call.caller_id.portal.release.no_calls'))
            ->assertSee(__('call.caller_id.portal.release.portal_locked'))
            // Ohne betroffene Leads steht die Zeile nicht da: Eine Null waere
            // eine Warnung ohne Gegenstand.
            ->assertDontSee(__('call.caller_id.portal.release.open_leads', ['count' => 1]));
    }

    public function test_die_rueckfrage_nennt_die_offenen_leads_und_die_naechste_frist(): void
    {
        $user = $this->actingAsBuyerWithNumber();

        // Die fruehere der beiden Fristen gehoert in den Satz -- sie ist die,
        // nach der sich der Nutzer richten muss (Ticket #4).
        $this->purchaseWithRunningDeadline($user, now()->addDays(5));
        $this->purchaseWithRunningDeadline($user, now()->addDays(2));

        Livewire::test(CallerIdVerification::class)
            ->call('confirmRelease')
            ->assertSee(trans_choice('call.caller_id.portal.release.open_leads_until', 2, [
                'count' => 2,
                'date' => now()->addDays(2)->format('d.m.Y'),
            ]));
    }

    public function test_abbrechen_laesst_die_nummer_stehen(): void
    {
        $user = $this->actingAsBuyerWithNumber();

        Livewire::test(CallerIdVerification::class)
            ->call('confirmRelease')
            ->call('cancelRelease')
            ->assertSet('confirmingRelease', false);

        $this->assertNotNull($user->fresh()->phone_number_verified_at);
    }

    public function test_nach_dem_zuruecknehmen_beginnt_die_bestaetigung_von_vorn(): void
    {
        $user = $this->actingAsBuyerWithNumber();

        Livewire::test(CallerIdVerification::class)
            ->call('confirmRelease')
            ->call('releaseNumber')
            ->assertRedirect(route('onboarding.phone'));

        $fresh = $user->fresh();

        $this->assertNull($fresh->phone_number_verified_at);
        $this->assertNull($fresh->phone_e164);

        // Kein Rest des vorherigen Durchlaufs: Die Zeile ist weg, der Screen
        // beginnt deshalb im ersten Zustand.
        $this->assertFalse(
            CallerId::query()->withoutGlobalScopes()->where('user_id', $user->getKey())->exists(),
        );
    }

    /**
     * Ein Kaeufer mit bestaetigter Nummer, angemeldet und im Workspace-Kontext.
     */
    private function actingAsBuyerWithNumber(): User
    {
        $tenant = Tenant::factory()->buyer()->create();
        $user = User::factory()->create();
        $tenant->users()->attach($user);

        $callerId = CallerId::factory()->verified()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $user->forceFill([
            'phone_e164' => $callerId->phone_number,
            'phone_number_verified_at' => now(),
        ])->save();

        $this->actingAs($user);
        Filament::setTenant($tenant);

        return $user;
    }

    /**
     * Ein eigener Kauf mit laufender Frist -- genau das, was
     * CallerIdService::countOpenLeadsAtRisk() zaehlt.
     */
    private function purchaseWithRunningDeadline(User $user, ?Carbon $deadline = null): LeadPurchase
    {
        $lead = Lead::factory()->create([
            'contact_status' => LeadContactStatus::OPEN,
            'deadline_at' => $deadline ?? now()->addDays(3),
        ]);

        return LeadPurchase::factory()->create([
            'lead_id' => $lead->getKey(),
            'buyer_tenant_id' => $user->tenants()->first()->getKey(),
            'purchased_by_user_id' => $user->getKey(),
        ]);
    }
}
