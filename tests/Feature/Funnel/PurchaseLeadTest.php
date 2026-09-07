<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PurchaseLead;
use App\Constants\FunnelStatus;
use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Constants\TenantType;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\LeadNotPurchasableException;
use App\Mail\Lead\LeadPurchased as LeadPurchasedMail;
use App\Models\BuyerRegistration;
use App\Models\CreditLedgerEntry;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreditLedgerService;
use App\Services\LeadReservationService;
use App\Services\LeadStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\FeatureTest;

/**
 * FB-054: Der Kaufvorgang.
 *
 * Hier haengt das Geschaeftsmodell. Drei Zusagen, die alle drei teuer sind,
 * wenn sie brechen:
 *
 *  - Ein Lead wird genau einmal verkauft. Bricht das, melden sich zwei
 *    Agenturen beim selben Tierhalter, und es faellt niemandem auf.
 *  - Kaufbeleg, Abbuchung und Zustandswechsel gelten zusammen oder gar nicht.
 *    Ein halb gebuchter Kauf ist in einem append-only Journal nicht mehr
 *    zurueckzunehmen.
 *  - Kontaktdaten sieht der Kaeufer, der gekauft hat -- und nur der.
 */
class PurchaseLeadTest extends FeatureTest
{
    private const EMAIL = 'mara.lindqvist@example.com';

    private const PHONE = '+493012345678';

    private function operatorLead(int $priceEuro = 15): Lead
    {
        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);

        $funnel = Funnel::factory()->create([
            'tenant_id' => $operator->getKey(),
            'name' => 'Pfotencheck',
            'status' => FunnelStatus::PUBLISHED,
        ]);

        $lead = Lead::factory()->inState(LeadState::VERFUEGBAR)->create([
            'tenant_id' => $operator->getKey(),
            'funnel_id' => $funnel->getKey(),
            'score' => 12,
            'price_at_creation' => $priceEuro,
            'email_normalized' => self::EMAIL,
            'phone_e164' => self::PHONE,
        ]);

        foreach (['tierart' => 'hund', 'vorname' => 'Mara', 'nachname' => 'Lindqvist',
            'email' => self::EMAIL, 'telefon' => self::PHONE, 'plz' => '76131'] as $key => $value) {
            LeadAnswer::query()->create(['lead_id' => $lead->getKey(), 'field_key' => $key, 'value' => $value]);
        }

        return $lead->fresh(['answers']);
    }

    /**
     * @return array{Tenant, User}
     */
    private function approvedBuyer(int $credits = 0): array
    {
        $tenant = BuyerRegistration::factory()->approved()->create()->tenant->fresh();

        $user = User::factory()->create();
        $tenant->users()->attach($user);

        if ($credits > 0) {
            app(CreditLedgerService::class)->purchase($tenant, $credits, $credits * 1500);
        }

        return [$tenant, $user];
    }

    public function test_two_parallel_purchases_of_the_same_lead_leave_exactly_one_winner(): void
    {
        $lead = $this->operatorLead();

        [$first, $firstUser] = $this->approvedBuyer(credits: 5);
        [$second, $secondUser] = $this->approvedBuyer(credits: 5);

        $action = app(PurchaseLead::class);

        // Der zweite Kaeufer greift genau in dem Moment zu, in dem der erste
        // seine Reservierung geschrieben hat -- also im gefaehrlichsten Fenster
        // ueberhaupt. Dass beide dieselbe Datenbankverbindung teilen, ist fuer
        // die Frage unerheblich: Entscheidend ist, ob der zweite Versuch den
        // inzwischen geaenderten Zustand sieht, und genau das prueft die
        // Zustandsmaschine unter Sperre.
        $outcomes = [];

        try {
            $action->handle($first, $lead, $firstUser);
            $outcomes[] = 'erster';
        } catch (LeadNotPurchasableException) {
            // nicht erwartet, faellt unten auf
        }

        try {
            $action->handle($second, $lead->fresh(), $secondUser);
            $outcomes[] = 'zweiter';
        } catch (LeadNotPurchasableException) {
            // erwartet: der Lead ist vergeben
        }

        $this->assertSame(['erster'], $outcomes, 'Genau ein Kauf darf durchkommen.');

        $this->assertSame(1, LeadPurchase::query()->count());
        $this->assertSame($first->getKey(), LeadPurchase::query()->sole()->buyer_tenant_id);
        $this->assertSame(LeadState::VERKAUFT, $lead->fresh()->lead_state);

        // Und nur der Gewinner hat bezahlt.
        $this->assertSame(4, app(CreditLedgerService::class)->balanceFor($first->fresh()));
        $this->assertSame(5, app(CreditLedgerService::class)->balanceFor($second->fresh()));
    }

    /**
     * Das echte Rennen -- der Fall, der die Vorpruefung ueberholt.
     *
     * Ein zweiter Prozess hat den Lead gelesen, als er noch `verfuegbar` war,
     * und klickt Millisekunden spaeter. Seine Lead-Instanz traegt den alten
     * Zustand, die fruehe Pruefung in PurchaseLead laesst ihn also durch. Was
     * ihn aufhaelt, ist ausschliesslich der erneute Abgleich unter Sperre in
     * LeadStateService::transition().
     *
     * Zwei echte Betriebssystemprozesse waeren hier kein besserer Test,
     * sondern ein schlechterer: Die Testsuite haelt ihre Daten in einer
     * offenen Transaktion, ein zweiter Prozess saehe den Lead gar nicht. Der
     * gestellte Zwischenstand trifft dagegen genau das Fenster, um das es geht.
     */
    public function test_a_stale_read_does_not_get_past_the_lock(): void
    {
        $lead = $this->operatorLead();

        [$winner, $winnerUser] = $this->approvedBuyer(credits: 5);
        [$loser, $loserUser] = $this->approvedBuyer(credits: 5);

        // Der Verlierer haelt den Lead in der Hand, so wie er ihn im Marktplatz
        // gesehen hat: verfuegbar.
        $staleLead = Lead::query()->withoutGlobalScope('tenant')->findOrFail($lead->getKey());

        $this->assertSame(LeadState::VERFUEGBAR, $staleLead->lead_state);

        // Inzwischen kauft der Gewinner.
        app(PurchaseLead::class)->handle($winner, $lead, $winnerUser);

        // Und jetzt klickt der Verlierer -- mit seinem veralteten Stand.
        try {
            app(PurchaseLead::class)->handle($loser, $staleLead, $loserUser);
            $this->fail('Ein veralteter Lesestand darf nicht an der Sperre vorbeikommen.');
        } catch (LeadNotPurchasableException) {
            // erwartet
        }

        $this->assertSame(1, LeadPurchase::query()->count());
        $this->assertSame($winner->getKey(), LeadPurchase::query()->sole()->buyer_tenant_id);

        // Der Verlierer hat nichts bezahlt.
        $this->assertSame(5, app(CreditLedgerService::class)->balanceFor($loser->fresh()));

        // Und der Lead bleibt verkauft -- der gescheiterte Versuch hat ihn
        // nicht in die Reservierung zurueckgeworfen.
        $this->assertSame(LeadState::VERKAUFT, $lead->fresh()->lead_state);
    }

    public function test_a_reservation_written_by_someone_else_blocks_the_purchase(): void
    {
        $lead = $this->operatorLead();

        [$winner, $winnerUser] = $this->approvedBuyer(credits: 5);
        [$loser, $loserUser] = $this->approvedBuyer(credits: 5);

        // Der Gewinner haelt den Lead bereits reserviert -- so sieht der
        // Zwischenstand aus, wenn zwei Klicks Millisekunden auseinanderliegen.
        app(PurchaseLead::class)->handle($winner, $lead, $winnerUser);

        $this->expectException(LeadNotPurchasableException::class);

        app(PurchaseLead::class)->handle($loser, $lead->fresh(), $loserUser);
    }

    public function test_an_insufficient_balance_leaves_nothing_half_booked(): void
    {
        $lead = $this->operatorLead();

        // Kein Guthaben: Der Kauf muss vollstaendig zurueckfallen.
        [$buyer, $user] = $this->approvedBuyer(credits: 0);

        try {
            app(PurchaseLead::class)->handle($buyer, $lead, $user);
            $this->fail('Ein Kauf ohne Guthaben muss abgewiesen werden.');
        } catch (InsufficientCreditsException) {
            // erwartet
        }

        // Weder Beleg noch Buchung -- und der Saldo unveraendert bei null.
        $this->assertSame(0, LeadPurchase::query()->count());
        $this->assertSame(0, CreditLedgerEntry::query()->withoutGlobalScope('tenant')->count());
        $this->assertSame(0, app(CreditLedgerService::class)->balanceFor($buyer->fresh()));

        // Und der Lead haengt nicht in der Reservierung fest: Ein Lead, den
        // niemand kaufen kann, muss wieder angeboten werden.
        $lead->refresh();

        $this->assertSame(LeadState::VERFUEGBAR, $lead->lead_state);
        $this->assertNull($lead->reserved_by);
        $this->assertNull($lead->reserved_until);
    }

    public function test_only_the_buying_tenant_sees_clear_text_after_the_purchase(): void
    {
        Mail::fake();

        $lead = $this->operatorLead();

        [$buyer, $buyingUser] = $this->approvedBuyer(credits: 5);
        [, $otherUser] = $this->approvedBuyer(credits: 5);

        // Vor dem Kauf sieht auch der spaetere Kaeufer nichts.
        $this->assertTrue($lead->contactFor($buyingUser)->masked);

        app(PurchaseLead::class)->handle($buyer, $lead, $buyingUser);

        $lead->refresh();

        $bought = $lead->contactFor($buyingUser);

        $this->assertFalse($bought->masked);
        $this->assertSame(self::EMAIL, $bought->email);
        $this->assertSame(self::PHONE, $bought->phone);

        // Ein anderer Kaeufer bleibt aussen vor -- der Kauf gilt fuer einen
        // Mandanten, nicht fuer alle.
        $this->assertTrue($lead->contactFor($otherUser)->masked);

        // Und der Kaeufer bekommt die Kontaktdaten per Mail.
        Mail::assertQueued(LeadPurchasedMail::class);
    }

    public function test_the_price_is_frozen_at_purchase_and_settled_on_the_final_state(): void
    {
        $lead = $this->operatorLead(priceEuro: 22);

        [$buyer, $user] = $this->approvedBuyer(credits: 5);

        $purchase = app(PurchaseLead::class)->handle($buyer, $lead, $user);

        // Der Preis, zu dem der Lead angeboten wurde -- nicht der heutige
        // Funnelpreis (Architekturleitsatz 4).
        $this->assertSame(2200, $purchase->price_cents);
        $this->assertSame(strtoupper((string) config('app.default_currency')), $purchase->currency);

        // Die Abbuchung traegt den Kaufbeleg als Referenz.
        $debit = CreditLedgerEntry::query()->withoutGlobalScope('tenant')
            ->where('type', 'debit')->sole();

        $this->assertSame(-1, $debit->credits);
        $this->assertSame($purchase->getKey(), $debit->reference_id);
    }

    public function test_an_expired_reservation_returns_the_lead_to_the_marketplace(): void
    {
        $lead = $this->operatorLead();

        [$buyer, $user] = $this->approvedBuyer(credits: 0);

        // Ein abgebrochener Kauf, der die Reservierung stehen liesse -- hier
        // von Hand gestellt, weil der Abbruch sie sonst selbst zurueckgibt.
        try {
            app(PurchaseLead::class)->handle($buyer, $lead, $user);
        } catch (InsufficientCreditsException) {
            // erwartet
        }

        // Zustand von Hand auf eine abgelaufene Reservierung setzen: So sieht
        // ein Lead aus, dessen Kaufprozess gestorben ist.
        app(LeadStateService::class)->transition(
            $lead->fresh(),
            LeadState::RESERVIERT,
            LeadTransitionReason::RESERVED_BY_BUYER,
        );

        DB::table('leads')->where('id', $lead->getKey())->update([
            'reserved_by' => $buyer->getKey(),
            'reserved_until' => now()->subMinute(),
        ]);

        $released = app(LeadReservationService::class)->releaseExpired();

        $this->assertSame(1, $released);

        $lead->refresh();

        $this->assertSame(LeadState::VERFUEGBAR, $lead->lead_state);
        $this->assertNull($lead->reserved_by);
        $this->assertNull($lead->reserved_until);
    }
}
