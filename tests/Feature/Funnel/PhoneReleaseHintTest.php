<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\CallAttemptOutcome;
use App\Constants\CallAttemptStatus;
use App\Constants\LeadContactStatus;
use App\Constants\LeadState;
use App\Constants\TenantType;
use App\Livewire\Portal\LeadDetail;
use App\Livewire\Portal\PurchasedLeads;
use App\Models\BuyerRegistration;
use App\Models\CallAttempt;
use App\Models\CallerId;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * Ticket #6: Das Portal erklaert den Stand der Rufnummernfreigabe.
 *
 * Geprueft wird, was der Kaeufer neben der verdeckten Nummer liest -- je
 * Grund ein Fall. Die Zahl im Hinweis muss die sein, die auch entscheidet
 * (eine Zaehlung, die der Freigabe), und in keinem verdeckten Fall darf die
 * echte Nummer im Markup stehen.
 */
class PhoneReleaseHintTest extends FeatureTest
{
    private const PHONE = '+493012345678';

    private Tenant $operator;

    private Tenant $buyer;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $this->buyer = BuyerRegistration::factory()->approved()->create()->tenant->fresh();
        $this->user = User::factory()->create();
        $this->buyer->users()->attach($this->user);

        CallerId::factory()->verified()->create([
            'tenant_id' => $this->buyer->getKey(),
            'user_id' => $this->user->getKey(),
        ]);
    }

    public function test_bei_verdeckter_nummer_steht_der_versuchsstand_daneben(): void
    {
        $purchase = $this->purchase();
        $this->attempt($purchase, CallAttemptOutcome::FAILED_VALID);

        $component = $this->detail($purchase);

        // Ein gezaehlter Anruf von drei -- dieselbe Zaehlung, die ueber die
        // Freigabe entscheidet.
        $component->assertSee(__('call.phone_release.pending', [
            'count' => 1,
            'required' => 3,
        ]), false);

        $component->assertDontSee(self::PHONE);
    }

    public function test_ein_ignorierter_versuch_zaehlt_im_hinweis_nicht_mit(): void
    {
        $purchase = $this->purchase();
        $this->attempt($purchase, CallAttemptOutcome::FAILED_VALID);
        $this->attempt($purchase, CallAttemptOutcome::FAILED_IGNORED);

        $this->detail($purchase)->assertSee(__('call.phone_release.pending', [
            'count' => 1,
            'required' => 3,
        ]), false);
    }

    public function test_nach_der_gutschrift_sagt_der_hinweis_warum_die_nummer_weg_ist(): void
    {
        $purchase = $this->purchase(LeadContactStatus::UNREACHABLE);

        // Der Kaeufer hatte sie nach dem dritten Anruf schon gesehen -- mit
        // der Gutschrift ist sie wieder verdeckt (Ticket #5).
        $this->attempt($purchase, CallAttemptOutcome::FAILED_VALID);
        $this->attempt($purchase, CallAttemptOutcome::FAILED_VALID);
        $this->attempt($purchase, CallAttemptOutcome::FAILED_VALID);

        $component = $this->detail($purchase);

        $component->assertSee(__('call.phone_release.unreachable'), false);
        $component->assertDontSee(self::PHONE);
    }

    public function test_ist_die_nummer_frei_steht_sie_im_klartext_und_der_hinweis_ist_weg(): void
    {
        $purchase = $this->purchase();
        $this->attempt($purchase, CallAttemptOutcome::ANSWERED);

        $component = $this->detail($purchase);

        $component
            ->assertSee(self::PHONE)
            ->assertSee(__('marketplace.purchased.portal.phone_hint_released'), false)
            ->assertDontSee(__('call.phone_release.unreachable'), false);
    }

    public function test_in_meinen_leads_fuehrt_die_freigabe_zur_nummer_statt_zum_anrufknopf(): void
    {
        $purchase = $this->purchase();

        $this->actingAs($this->user);
        Filament::setTenant($this->buyer, isQuiet: true);

        $startCall = 'startCall('.$purchase->getKey().')';

        // Noch verdeckt: Der Portalknopf ist der einzige Weg zum Lead.
        Livewire::test(PurchasedLeads::class)
            ->assertSee($startCall, false)
            ->assertDontSee('tel:'.self::PHONE, false);

        $this->attempt($purchase, CallAttemptOutcome::ANSWERED);

        // Frei, der Lead aber weiter offen: Ab hier waehlt der Kaeufer selbst.
        // Vorher hing dieser Knopf am Abrechnungsstand und bot in genau
        // diesem Fall noch den Anruf ueber die Bridge an.
        Livewire::test(PurchasedLeads::class)
            ->assertSee('tel:'.self::PHONE, false)
            ->assertDontSee($startCall, false);
    }

    /**
     * Ticket #100: Der `tel:`-Verweis ersetzt den Knopf nur bei einer
     * endgueltigen Freigabe. Abgerechnet -- da entscheidet kein Anruf mehr.
     */
    public function test_bei_abrechnung_ersetzt_der_verweis_den_anrufknopf(): void
    {
        $purchase = $this->purchase(LeadContactStatus::BILLABLE);

        $this->detail($purchase)
            ->assertSee('tel:'.self::PHONE, false)
            ->assertDontSee('startCall', false);
    }

    /**
     * Ticket #100: Ein angenommenes Gespraech ist ebenso endgueltig.
     */
    public function test_nach_einem_angenommenen_gespraech_ersetzt_der_verweis_den_anrufknopf(): void
    {
        $purchase = $this->purchase();
        $this->attempt($purchase, CallAttemptOutcome::ANSWERED);

        $this->detail($purchase)
            ->assertSee('tel:'.self::PHONE, false)
            ->assertDontSee('startCall', false);
    }

    /**
     * Ticket #100: Die Freigabe auf Zeit laesst den Knopf stehen.
     *
     * Hier entscheidet der Anruf noch ueber Gutschrift oder Abrechnung. Ein
     * Gespraech ueber einen `tel:`-Verweis erzeugt keinen Versuch -- der Lead
     * waere gutgeschrieben worden, obwohl der Kontakt zustande kam. Die Nummer
     * steht daneben trotzdem im Klartext, nur nicht als Verweis.
     */
    public function test_bei_der_freigabe_auf_zeit_bleibt_der_anrufknopf_stehen(): void
    {
        $purchase = $this->purchase();
        $this->attempt($purchase, CallAttemptOutcome::FAILED_VALID);
        $this->attempt($purchase, CallAttemptOutcome::FAILED_VALID);
        $this->attempt($purchase, CallAttemptOutcome::FAILED_VALID);

        $this->detail($purchase)
            ->assertSee('startCall', false)
            ->assertDontSee('tel:'.self::PHONE, false)
            ->assertSee(self::PHONE)
            ->assertSee(__('marketplace.purchased.portal.phone_hint_released_on_time'), false);
    }

    /**
     * Rendert die Detailseite als der Kaeufer.
     */
    private function detail(LeadPurchase $purchase): Testable
    {
        $this->actingAs($this->user);

        // Den Mandanten setzt sonst die Routen-Middleware; im
        // Komponententest gibt es sie nicht (InteractsWithPortalTenant).
        Filament::setTenant($this->buyer, isQuiet: true);

        return Livewire::test(LeadDetail::class, ['purchase' => (string) $purchase->getKey()]);
    }

    private function purchase(LeadContactStatus $contactStatus = LeadContactStatus::OPEN): LeadPurchase
    {
        $lead = Lead::factory()->inState(LeadState::VERKAUFT)->create([
            'tenant_id' => $this->operator->getKey(),
            'phone_e164' => self::PHONE,
            'contact_status' => $contactStatus,
        ]);

        return LeadPurchase::factory()->create([
            'lead_id' => $lead->getKey(),
            'buyer_tenant_id' => $this->buyer->getKey(),
            'purchased_by_user_id' => $this->user->getKey(),
        ]);
    }

    private function attempt(LeadPurchase $purchase, CallAttemptOutcome $outcome): void
    {
        $attempt = CallAttempt::factory()->create([
            'tenant_id' => $purchase->buyer_tenant_id,
            'lead_purchase_id' => $purchase->getKey(),
            'lead_id' => $purchase->lead_id,
            'user_id' => $this->user->getKey(),
            'lead_number' => self::PHONE,
        ]);

        // `outcome` und `status` sind am Modell geschuetzt: Geschrieben werden
        // sie nur vom CallService im Rueckruf von Twilio.
        $attempt->forceFill([
            'status' => CallAttemptStatus::COMPLETED,
            'outcome' => $outcome,
            'duration_seconds' => $outcome === CallAttemptOutcome::ANSWERED ? 45 : 0,
        ])->save();
    }
}
