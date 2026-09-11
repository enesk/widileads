<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PurchaseLead;
use App\Constants\FunnelStatus;
use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Constants\TenantType;
use App\Constants\WalletTransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\LeadNotPurchasableException;
use App\Mail\Lead\LeadPurchased as LeadPurchasedMail;
use App\Models\BuyerRegistration;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\LeadReservationService;
use App\Services\LeadStateService;
use App\Services\Wallet\WalletService;
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

    private function operatorLead(int $priceCents = 1500): Lead
    {
        // Der Verkaufspreis haengt seit LP-WALLET-003 am Verkaeufer-Mandanten,
        // nicht mehr am Lead: Der PurchaseService liest tenants.lead_price_cents
        // und schreibt ihn erst im Kaufbeleg fest.
        $operator = Tenant::factory()->create([
            'type' => TenantType::OPERATOR,
            'lead_price_cents' => $priceCents,
        ]);

        $funnel = Funnel::factory()->create([
            'tenant_id' => $operator->getKey(),
            'name' => 'Pfotencheck',
            'status' => FunnelStatus::PUBLISHED,
        ]);

        $lead = Lead::factory()->inState(LeadState::VERFUEGBAR)->create([
            'tenant_id' => $operator->getKey(),
            'funnel_id' => $funnel->getKey(),
            'score' => 12,
            'price_at_creation' => $priceCents / 100,
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
    private function approvedBuyer(int $balanceCents = 0): array
    {
        $tenant = BuyerRegistration::factory()->approved()->create()->tenant->fresh();

        $user = User::factory()->create();
        $tenant->users()->attach($user);

        if ($balanceCents > 0) {
            app(WalletService::class)->post(
                wallet: Wallet::forBuyer($tenant),
                type: WalletTransactionType::TOPUP,
                amountCents: $balanceCents,
                description: 'Aufladung im Test',
            );
        }

        return [$tenant, $user];
    }

    /**
     * Der frei verfuegbare Saldo eines Kaeufers in Cent -- also das, was er
     * noch ausgeben kann, abzueglich der geblockten Reservierungen.
     */
    private function availableCentsOf(Tenant $buyer): int
    {
        return (int) Wallet::forBuyer($buyer)->refresh()->available_cents;
    }

    public function test_two_parallel_purchases_of_the_same_lead_leave_exactly_one_winner(): void
    {
        $lead = $this->operatorLead();

        [$first, $firstUser] = $this->approvedBuyer(balanceCents: 7500);
        [$second, $secondUser] = $this->approvedBuyer(balanceCents: 7500);

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

        // Und nur beim Gewinner ist Geld geblockt: Der Kaufpreis steht als
        // Reservierung auf seinem Wallet, der Verlierer hat den vollen Betrag
        // weiterhin frei.
        $this->assertSame(6000, $this->availableCentsOf($first));
        $this->assertSame(7500, $this->availableCentsOf($second));
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

        [$winner, $winnerUser] = $this->approvedBuyer(balanceCents: 7500);
        [$loser, $loserUser] = $this->approvedBuyer(balanceCents: 7500);

        // Der Verlierer haelt den Lead in der Hand, so wie er ihn im Marktplatz
        // gesehen hat: verfuegbar.
        $staleLead = Lead::query()->withoutGlobalScope('tenant')->findOrFail($lead->getKey());

        $this->assertSame(LeadState::VERFUEGBAR, $staleLead->lead_state);

        // Inzwischen kauft der Gewinner. Dabei wird mitgeschnitten, welche
        // Abfragen laufen -- siehe die Sperr-Assertion unten.
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(PurchaseLead::class)->handle($winner, $lead, $winnerUser);

        $queries = array_column(DB::getRawQueryLog(), 'raw_query');
        DB::disableQueryLog();

        // Der Abgleich unter Sperre ist nur die halbe Zusage -- die andere ist,
        // dass ueberhaupt gesperrt wird. Faellt das lockForUpdate() bei einem
        // spaeteren Umbau weg, bliebe die Nachstellung unten trotzdem gruen:
        // Der Abgleich greift dort auch ohne Sperre, weil nichts wirklich
        // gleichzeitig laeuft. In echter Nebenlaeufigkeit koennten dann aber
        // beide Transaktionen `verfuegbar` lesen und beide durchlaufen.
        $lockingReads = array_values(array_filter(
            $queries,
            static fn (string $query): bool => str_contains(strtolower($query), 'for update')
                && str_contains(strtolower($query), 'leads'),
        ));

        $this->assertNotSame([], $lockingReads, 'Der Lead muss zum Reservieren gesperrt gelesen werden.');

        // Und die Sperre steht vor dem Zustandswechsel, nicht danach -- sonst
        // schuetzte sie nichts.
        $firstLock = array_key_first(array_filter(
            $queries,
            static fn (string $query): bool => str_contains(strtolower($query), 'for update'),
        ));

        $firstStateWrite = array_key_first(array_filter(
            $queries,
            static fn (string $query): bool => str_starts_with(strtolower(trim($query)), 'update `leads`')
                && str_contains(strtolower($query), 'lead_state'),
        ));

        $this->assertNotNull($firstStateWrite, 'Der Zustandswechsel muss im Mitschnitt auftauchen.');
        $this->assertLessThan($firstStateWrite, $firstLock, 'Erst sperren, dann schreiben.');

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
        $this->assertSame(7500, $this->availableCentsOf($loser));

        // Und der Lead bleibt verkauft -- der gescheiterte Versuch hat ihn
        // nicht in die Reservierung zurueckgeworfen.
        $this->assertSame(LeadState::VERKAUFT, $lead->fresh()->lead_state);
    }

    public function test_a_reservation_written_by_someone_else_blocks_the_purchase(): void
    {
        $lead = $this->operatorLead();

        [$winner, $winnerUser] = $this->approvedBuyer(balanceCents: 7500);
        [$loser, $loserUser] = $this->approvedBuyer(balanceCents: 7500);

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
        [$buyer, $user] = $this->approvedBuyer(balanceCents: 0);

        try {
            app(PurchaseLead::class)->handle($buyer, $lead, $user);
            $this->fail('Ein Kauf ohne Guthaben muss abgewiesen werden.');
        } catch (InsufficientFundsException) {
            // erwartet
        }

        // Weder Beleg noch Buchung -- und beide Salden unveraendert bei null.
        $this->assertSame(0, LeadPurchase::query()->count());
        $this->assertSame(0, WalletTransaction::query()->count());

        $wallet = Wallet::forBuyer($buyer)->refresh();

        $this->assertSame(0, (int) $wallet->balance_cents);
        $this->assertSame(0, (int) $wallet->reserved_cents);

        // Und der Lead haengt nicht in der Reservierung fest: Ein Lead, den
        // niemand kaufen kann, muss wieder angeboten werden.
        $lead->refresh();

        $this->assertSame(LeadState::VERFUEGBAR, $lead->lead_state);
        $this->assertNull($lead->reserved_by);
        $this->assertNull($lead->reserved_until);
    }

    /**
     * Der Verkaeufer hebt seinen Preis an, waehrend der Kaeufer die
     * Marktplatzseite offen hat (LP-WALLET-007).
     *
     * Der Klick darf dann nicht stillschweigend teurer abgerechnet werden. Und
     * weil der Kauf schon vor der Reservierung scheitert, bleibt der Lead im
     * Angebot -- ohne Beleg, ohne geblocktes Geld.
     */
    public function test_a_price_changed_since_the_page_was_rendered_stops_the_purchase(): void
    {
        $lead = $this->operatorLead(priceCents: 1500);

        [$buyer, $user] = $this->approvedBuyer(balanceCents: 7500);

        try {
            // Der Kaeufer hat noch 12,00 EUR auf dem Bildschirm stehen.
            app(PurchaseLead::class)->handle($buyer, $lead, $user, priceShownCents: 1200);
            $this->fail('Ein geaenderter Preis muss den Kauf abweisen.');
        } catch (LeadNotPurchasableException $exception) {
            $this->assertSame(__('marketplace.purchase.errors.price_changed'), $exception->getMessage());
        }

        $this->assertSame(0, LeadPurchase::query()->count());
        $this->assertSame(0, WalletTransaction::query()->where('type', WalletTransactionType::RESERVE)->count());
        $this->assertSame(7500, $this->availableCentsOf($buyer));

        $lead->refresh();

        $this->assertSame(LeadState::VERFUEGBAR, $lead->lead_state);
        $this->assertNull($lead->reserved_by);
        $this->assertNull($lead->reserved_until);
    }

    /**
     * Derselbe Preis, wie die Seite ihn genannt hat -- der Regelfall, der
     * durchgehen muss. Ohne ihn belegte der Test oben nur, dass die Pruefung
     * ueberhaupt etwas abweist.
     */
    public function test_the_displayed_price_matching_the_sellers_price_goes_through(): void
    {
        Mail::fake();

        $lead = $this->operatorLead(priceCents: 1500);

        [$buyer, $user] = $this->approvedBuyer(balanceCents: 7500);

        $purchase = app(PurchaseLead::class)->handle($buyer, $lead, $user, priceShownCents: 1500);

        $this->assertSame(1500, $purchase->price_cents);
        $this->assertSame(6000, $this->availableCentsOf($buyer));
    }

    public function test_only_the_buying_tenant_sees_clear_text_after_the_purchase(): void
    {
        Mail::fake();

        $lead = $this->operatorLead();

        [$buyer, $buyingUser] = $this->approvedBuyer(balanceCents: 7500);
        [, $otherUser] = $this->approvedBuyer(balanceCents: 7500);

        // Vor dem Kauf sieht auch der spaetere Kaeufer nichts.
        $this->assertTrue($lead->contactFor($buyingUser)->masked);

        app(PurchaseLead::class)->handle($buyer, $lead, $buyingUser);

        $lead->refresh();

        $bought = $lead->contactFor($buyingUser);

        $this->assertFalse($bought->masked);
        $this->assertSame(self::EMAIL, $bought->email);

        // Die Rufnummer bleibt trotz Kauf verdeckt, bis die
        // Erreichbarkeitspruefung mit `billable` geendet hat (FB-085) --
        // angerufen wird bis dahin ueber die Bridge.
        $this->assertTrue($bought->phoneMasked);
        $this->assertSame($lead->maskedPhone(), $bought->phone);

        // Ein anderer Kaeufer bleibt aussen vor -- der Kauf gilt fuer einen
        // Mandanten, nicht fuer alle.
        $this->assertTrue($lead->contactFor($otherUser)->masked);

        // Und der Kaeufer bekommt die Kontaktdaten per Mail.
        Mail::assertQueued(LeadPurchasedMail::class);
    }

    public function test_the_price_is_frozen_at_purchase_and_settled_on_the_final_state(): void
    {
        $lead = $this->operatorLead(priceCents: 2200);

        [$buyer, $user] = $this->approvedBuyer(balanceCents: 7500);

        $purchase = app(PurchaseLead::class)->handle($buyer, $lead, $user);

        // Der Preis, den der Verkaeufer beim Kauf verlangt hat -- danach im
        // Beleg festgeschrieben und nie wieder nachgeschlagen
        // (Architekturleitsatz 4).
        $this->assertSame(2200, $purchase->price_cents);
        $this->assertSame((string) config('wallet.currency'), $purchase->currency);

        // Geblockt, nicht abgebucht: Bezahlt wird erst, wenn der Lead
        // abgerechnet ist (LP-WALLET-008).
        $wallet = Wallet::forBuyer($buyer)->refresh();

        $this->assertSame(7500, (int) $wallet->balance_cents);
        $this->assertSame(2200, (int) $wallet->reserved_cents);

        // Und die Buchung traegt den Kaufbeleg als Referenz.
        $reservation = WalletTransaction::query()
            ->where('type', WalletTransactionType::RESERVE)->sole();

        $this->assertSame(2200, (int) $reservation->amount_cents);
        $this->assertSame($purchase->getKey(), $reservation->reference_id);
        $this->assertSame(LeadPurchase::class, $reservation->reference_type);
    }

    public function test_an_expired_reservation_returns_the_lead_to_the_marketplace(): void
    {
        $lead = $this->operatorLead();

        [$buyer, $user] = $this->approvedBuyer(balanceCents: 0);

        // Ein abgebrochener Kauf, der die Reservierung stehen liesse -- hier
        // von Hand gestellt, weil der Abbruch sie sonst selbst zurueckgibt.
        try {
            app(PurchaseLead::class)->handle($buyer, $lead, $user);
        } catch (InsufficientFundsException) {
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
