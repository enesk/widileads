<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\CallAttemptOutcome;
use App\Constants\CallAttemptStatus;
use App\Constants\LeadContactStatus;
use App\Constants\LeadState;
use App\Constants\PhoneReleaseReason;
use App\Constants\TenantType;
use App\Models\BuyerRegistration;
use App\Models\CallAttempt;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use Tests\Feature\FeatureTest;

/**
 * Ticket #5: Wann der Kaeufer die Rufnummer bekommt.
 *
 * Die Rufnummer ist das eigentliche Produkt, und sie ist die einzige
 * Kontaktangabe, die der Kauf allein nicht freigibt. Geprueft wird deshalb
 * beides: dass sie herauskommt, wenn der Kaeufer gearbeitet hat -- und dass
 * sie verdeckt bleibt, wo die Bedingung nur so aussieht, als waere sie
 * erfuellt.
 */
class PhoneReleaseTest extends FeatureTest
{
    private const PHONE = '+493012345678';

    private Tenant $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
    }

    private function soldLead(LeadContactStatus $contactStatus = LeadContactStatus::OPEN): Lead
    {
        return Lead::factory()->inState(LeadState::VERKAUFT)->create([
            'tenant_id' => $this->operator->getKey(),
            'phone_e164' => self::PHONE,
            'contact_status' => $contactStatus,
        ]);
    }

    /**
     * @return array{Tenant, User, LeadPurchase}
     */
    private function buyerWithPurchase(Lead $lead): array
    {
        $tenant = BuyerRegistration::factory()->approved()->create()->tenant->fresh();
        $user = User::factory()->create();
        $tenant->users()->attach($user);

        $purchase = LeadPurchase::factory()->create([
            'lead_id' => $lead->getKey(),
            'buyer_tenant_id' => $tenant->getKey(),
            'purchased_by_user_id' => $user->getKey(),
        ]);

        return [$tenant, $user->fresh(), $purchase];
    }

    private function attempt(LeadPurchase $purchase, User $user, CallAttemptOutcome $outcome): CallAttempt
    {
        $attempt = CallAttempt::factory()->create([
            'tenant_id' => $purchase->buyer_tenant_id,
            'lead_purchase_id' => $purchase->getKey(),
            'lead_id' => $purchase->lead_id,
            'user_id' => $user->getKey(),
            'lead_number' => self::PHONE,
        ]);

        // `outcome` und `status` sind am Modell geschuetzt: Geschrieben werden
        // sie nur vom CallService im Rueckruf von Twilio.
        $attempt->forceFill([
            'status' => CallAttemptStatus::COMPLETED,
            'outcome' => $outcome,
            'duration_seconds' => $outcome === CallAttemptOutcome::ANSWERED ? 45 : 0,
        ])->save();

        return $attempt;
    }

    public function test_an_answered_call_releases_the_phone_number(): void
    {
        $lead = $this->soldLead();
        [, $user, $purchase] = $this->buyerWithPurchase($lead);

        $this->attempt($purchase, $user, CallAttemptOutcome::ANSWERED);

        $contact = $lead->fresh()->contactFor($user);

        $this->assertFalse($contact->phoneMasked);
        $this->assertSame(self::PHONE, $contact->phone);
    }

    public function test_the_third_counted_attempt_releases_the_phone_number(): void
    {
        $lead = $this->soldLead();
        [, $user, $purchase] = $this->buyerWithPurchase($lead);

        $this->attempt($purchase, $user, CallAttemptOutcome::FAILED_VALID);
        $this->attempt($purchase, $user, CallAttemptOutcome::FAILED_VALID);

        // Zwei Versuche sind noch keine Erreichbarkeitspruefung.
        $this->assertTrue($lead->fresh()->contactFor($user)->phoneMasked);

        $this->attempt($purchase, $user, CallAttemptOutcome::FAILED_VALID);

        $contact = $lead->fresh()->contactFor($user);

        $this->assertFalse($contact->phoneMasked);
        $this->assertSame(self::PHONE, $contact->phone);
    }

    public function test_ignored_attempts_do_not_release_the_phone_number(): void
    {
        $lead = $this->soldLead();
        [, $user, $purchase] = $this->buyerWithPurchase($lead);

        // Drei Versuche, die alle in der Sperrzeit oder zu frueh ausgeloest
        // wurden: Sie kosten den Kaeufer nichts und bringen ihm nichts.
        for ($i = 0; $i < 3; $i++) {
            $this->attempt($purchase, $user, CallAttemptOutcome::FAILED_IGNORED);
        }

        $this->assertTrue($lead->fresh()->contactFor($user)->phoneMasked);
    }

    public function test_the_attempts_of_another_buyer_release_nothing(): void
    {
        $lead = $this->soldLead();
        [, $firstUser, $firstPurchase] = $this->buyerWithPurchase($lead);
        [, $secondUser] = $this->buyerWithPurchase($lead);

        for ($i = 0; $i < 3; $i++) {
            $this->attempt($firstPurchase, $firstUser, CallAttemptOutcome::FAILED_VALID);
        }

        // Bei einem geteilten Lead (FB-055) oeffnet der Fleiss des einen
        // Kaeufers dem anderen nichts.
        $this->assertFalse($lead->fresh()->contactFor($firstUser)->phoneMasked);
        $this->assertTrue($lead->fresh()->contactFor($secondUser)->phoneMasked);
    }

    public function test_the_credit_takes_the_released_number_away_again(): void
    {
        $lead = $this->soldLead();
        [$tenant, $user, $purchase] = $this->buyerWithPurchase($lead);

        for ($i = 0; $i < 3; $i++) {
            $this->attempt($purchase, $user, CallAttemptOutcome::FAILED_VALID);
        }

        // Nach dem dritten gezaehlten Versuch steht die Nummer da -- und der
        // Abruf hat sie protokolliert.
        $this->assertFalse($lead->fresh()->contactFor($user)->phoneMasked);
        $this->assertNotNull($lead->fresh()->phone_revealed_at);

        $lead->forceFill([
            'contact_status' => LeadContactStatus::UNREACHABLE,
            'resolved_at' => now(),
        ])->save();

        $contact = $lead->fresh()->contactFor($user);

        // Entweder Geld oder Nummer, nicht beides: Mit der Gutschrift ist die
        // Freigabe zurueckgenommen, auch rueckwirkend.
        $this->assertTrue($contact->phoneMasked, 'Die Gutschrift nimmt die Freigabe zurueck.');
        $this->assertNotSame(self::PHONE, $contact->phone);

        // Das Protokoll bleibt stehen -- es haelt die Anzeige aber nicht offen.
        $this->assertNotNull($lead->fresh()->phone_revealed_at);

        $release = $lead->fresh()->phoneRelease($tenant);

        $this->assertFalse($release->released);
        $this->assertSame(PhoneReleaseReason::REVOKED_CREDIT, $release->reason);
        $this->assertTrue($release->isRevoked());
    }

    public function test_the_release_state_names_the_counted_attempts_and_the_reason(): void
    {
        $lead = $this->soldLead();
        [$tenant, $user, $purchase] = $this->buyerWithPurchase($lead);

        $fresh = $lead->fresh()->phoneRelease($tenant);

        $this->assertFalse($fresh->released);
        $this->assertSame(PhoneReleaseReason::LOCKED, $fresh->reason);
        $this->assertSame(0, $fresh->countedAttempts);
        $this->assertSame(3, $fresh->requiredAttempts);
        $this->assertSame(3, $fresh->remainingAttempts());

        // Ein ignorierter Versuch bewegt den Stand nicht.
        $this->attempt($purchase, $user, CallAttemptOutcome::FAILED_IGNORED);
        $this->attempt($purchase, $user, CallAttemptOutcome::FAILED_VALID);

        $started = $lead->fresh()->phoneRelease($tenant);

        $this->assertSame(1, $started->countedAttempts);
        $this->assertSame(2, $started->remainingAttempts());

        // Ein angenommenes Gespraech gibt frei und zaehlt zugleich mit: Die
        // Zahl im Hinweis ist dieselbe, die entscheidet.
        $this->attempt($purchase, $user, CallAttemptOutcome::ANSWERED);

        $answered = $lead->fresh()->phoneRelease($tenant);

        $this->assertTrue($answered->released);
        $this->assertSame(PhoneReleaseReason::ANSWERED, $answered->reason);
        $this->assertSame(2, $answered->countedAttempts);
        $this->assertSame(0, $answered->remainingAttempts());
    }

    public function test_the_third_counted_attempt_releases_on_time_only(): void
    {
        $lead = $this->soldLead();
        [$tenant, $user, $purchase] = $this->buyerWithPurchase($lead);

        for ($i = 0; $i < 3; $i++) {
            $this->attempt($purchase, $user, CallAttemptOutcome::FAILED_VALID);
        }

        $release = $lead->fresh()->phoneRelease($tenant);

        $this->assertTrue($release->released);
        $this->assertSame(PhoneReleaseReason::ATTEMPTS, $release->reason);
    }

    public function test_billing_releases_the_number_without_a_single_attempt(): void
    {
        $lead = $this->soldLead(LeadContactStatus::BILLABLE);
        [$tenant, $user] = $this->buyerWithPurchase($lead);

        $this->assertFalse($lead->fresh()->contactFor($user)->phoneMasked);
        $this->assertSame(PhoneReleaseReason::BILLABLE, $lead->fresh()->phoneRelease($tenant)->reason);
    }

    public function test_billing_after_an_answered_call_keeps_the_number_visible(): void
    {
        $lead = $this->soldLead();
        [$tenant, $user, $purchase] = $this->buyerWithPurchase($lead);

        $this->attempt($purchase, $user, CallAttemptOutcome::ANSWERED);

        $lead->forceFill([
            'contact_status' => LeadContactStatus::BILLABLE,
            'resolved_at' => now(),
        ])->save();

        $contact = $lead->fresh()->contactFor($user);

        $this->assertFalse($contact->phoneMasked);
        $this->assertSame(self::PHONE, $contact->phone);
        $this->assertTrue($lead->fresh()->phoneRelease($tenant)->released);
    }

    public function test_without_a_purchase_the_number_stays_masked(): void
    {
        $lead = $this->soldLead();
        [, $buyingUser, $purchase] = $this->buyerWithPurchase($lead);

        for ($i = 0; $i < 3; $i++) {
            $this->attempt($purchase, $buyingUser, CallAttemptOutcome::FAILED_VALID);
        }

        $outsider = $this->createUser(Tenant::factory()->create(['type' => TenantType::BUYER]));

        $contact = $lead->fresh()->contactFor($outsider);

        $this->assertTrue($contact->masked);
        $this->assertNotSame(self::PHONE, $contact->phone);
    }

    public function test_the_team_view_keeps_the_number_masked_even_after_release(): void
    {
        $lead = $this->soldLead();
        [, $user, $purchase] = $this->buyerWithPurchase($lead);

        $this->attempt($purchase, $user, CallAttemptOutcome::ANSWERED);

        $this->assertFalse($lead->fresh()->contactFor($user)->phoneMasked);

        // Dieselbe Freigabe, andere Sicht: "Team Leads" zeigt die Nummer
        // niemandem (Ticket #3).
        $teamContact = $lead->fresh()->contactForTeamView();

        $this->assertTrue($teamContact->phoneMasked);
        $this->assertNotSame(self::PHONE, $teamContact->phone);
    }
}
