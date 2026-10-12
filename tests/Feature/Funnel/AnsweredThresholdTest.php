<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\CallAttemptOutcome;
use App\Constants\CallAttemptStatus;
use App\Constants\LeadContactStatus;
use App\Constants\LeadState;
use App\Constants\TenantType;
use App\Models\BuyerRegistration;
use App\Models\CallAttempt;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AttemptClassifier;
use Tests\Feature\FeatureTest;

/**
 * Ticket #43: Die Schwelle "ab wann gilt ein Anruf als angenommen" steht an
 * genau einer Stelle.
 *
 * An dieser Zahl haengt Geld: Sie entscheidet, ob ein Lead abgerechnet oder
 * gutgeschrieben wird, und seit Ticket #5 auch, ob der Kaeufer die Rufnummer
 * bekommt. Bis hierher lag dieselbe Regel zweimal im Repo --
 * `funnel.call.answered_after_seconds` mit 20 und
 * `lead_calls.answered_min_seconds` mit 30. Jede Umgebung ohne die passenden
 * Zeilen in der `.env` rechnete also mit zwei verschiedenen Schwellen.
 *
 * Geprueft wird deshalb nicht der Konfigurationswert, sondern das Verhalten:
 * Wird die Schwelle verstellt, muss sich die Bewertung desselben Anrufs
 * aendern. Nur so ist belegt, dass die Klassifizierung wirklich an dieser
 * Konfiguration haengt und nicht an einer zweiten.
 */
class AnsweredThresholdTest extends FeatureTest
{
    /**
     * Ein abgeschlossener Anruf mit menschlichem Gegenueber und dieser
     * Gespraechsdauer -- noch unbewertet.
     */
    private function completedCall(int $durationSeconds): CallAttempt
    {
        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);

        $lead = Lead::factory()->inState(LeadState::VERKAUFT)->create([
            'tenant_id' => $operator->getKey(),
            'phone_e164' => '+4989'.fake()->numerify('#######'),
            'contact_status' => LeadContactStatus::OPEN,
        ]);

        $buyer = BuyerRegistration::factory()->approved()->create()->tenant->fresh();
        $user = User::factory()->create();
        $buyer->users()->attach($user);

        $purchase = LeadPurchase::factory()->create([
            'lead_id' => $lead->getKey(),
            'buyer_tenant_id' => $buyer->getKey(),
            'purchased_by_user_id' => $user->getKey(),
        ]);

        $attempt = CallAttempt::factory()->create([
            'tenant_id' => $buyer->getKey(),
            'lead_purchase_id' => $purchase->getKey(),
            'lead_id' => $lead->getKey(),
            'user_id' => $user->getKey(),
        ]);

        // Die Belege von Twilio: abgenommen, von einem Menschen, so lange
        // gesprochen. Die Bewertung daraus macht allein der Klassifizierer.
        $attempt->forceFill([
            'status' => CallAttemptStatus::COMPLETED,
            'dial_status' => 'completed',
            'answered_by' => 'human',
            'duration_seconds' => $durationSeconds,
        ])->save();

        return $attempt->fresh();
    }

    public function test_the_classification_follows_the_configured_threshold(): void
    {
        config()->set('lead_calls.answered_min_seconds', 30);

        $tooShort = app(AttemptClassifier::class)->classify($this->completedCall(25));

        $this->assertSame(CallAttemptOutcome::SHORT_CONTACT, $tooShort->outcome);

        // Dieselbe Gespraechsdauer, niedrigere Schwelle: Jetzt ist derselbe
        // Anruf erreicht. Waere die Schwelle woanders festgeschrieben, aenderte
        // sich hier nichts.
        config()->set('lead_calls.answered_min_seconds', 20);

        $longEnough = app(AttemptClassifier::class)->classify($this->completedCall(25));

        $this->assertSame(CallAttemptOutcome::ANSWERED, $longEnough->outcome);
    }

    public function test_the_abandoned_funnel_key_is_gone_for_good(): void
    {
        // Kein Alias, kein Rest: Der zweite Schluessel darf nicht wieder
        // auftauchen, sonst steht das Problem in einem Jahr erneut da.
        $this->assertNull(config('funnel.call.answered_after_seconds'));
        $this->assertStringNotContainsString(
            'FUNNEL_CALL_ANSWERED_AFTER_SECONDS',
            (string) file_get_contents(base_path('.env.example')),
        );
        $this->assertSame(30, (int) config('lead_calls.answered_min_seconds'));
    }
}
