<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\ComplaintStatus;
use App\Constants\LeadState;
use App\Constants\PurchaseStatus;
use App\Constants\TenantType;
use App\Constants\WalletTransactionType;
use App\Exceptions\ComplaintNotAllowedException;
use App\Models\BuyerRegistration;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\LeadComplaintService;
use App\Services\Wallet\PurchaseService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\FeatureTest;

/**
 * FB-058: Reklamation.
 *
 * Bis der Anrufnachweis kommt (FB-E7, nicht im Lieferumfang) ist das der
 * einzige Weg von `verkauft` nach `erreicht` oder `unerreichbar` -- also der
 * Weg, ueber den die gesamte Abrechnung laeuft. Geprueft wird deshalb beides,
 * was Geld bewegt: dass eine anerkannte Reklamation korrekt gutschreibt, und
 * dass die Frist korrekt ablaeuft.
 */
class LeadComplaintTest extends FeatureTest
{
    /**
     * @return array{LeadPurchase, Tenant}
     */
    private function purchase(int $boughtDaysAgo = 0): array
    {
        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $buyer = BuyerRegistration::factory()->approved()->create()->tenant->fresh();
        $buyer->users()->attach(User::factory()->create());

        $lead = Lead::factory()->inState(LeadState::VERKAUFT)->create([
            'tenant_id' => $operator->getKey(),
            'score' => 10,
        ]);

        app(WalletService::class)->post(
            wallet: Wallet::forBuyer($buyer),
            type: WalletTransactionType::TOPUP,
            amountCents: 7500,
            description: 'Aufladung im Test',
        );

        // Der Kauf ist abgerechnet: Der Kaeufer hat 15,00 EUR bezahlt, der
        // Verkaeufer seinen Anteil bekommen. Genau diesen Weg muss die
        // anerkannte Reklamation zurueckdrehen (LP-WALLET-006).
        $purchases = app(PurchaseService::class);

        $purchase = $purchases->capture($purchases->reserve($lead, $buyer));

        DB::table('lead_purchases')->where('id', $purchase->getKey())
            ->update(['purchased_at' => now()->subDays($boughtDaysAgo)]);

        return [$purchase->fresh(), $buyer];
    }

    public function test_an_accepted_complaint_moves_the_lead_and_refunds_the_money(): void
    {
        [$purchase, $buyer] = $this->purchase();
        $admin = $this->createAdminUser();
        $service = app(LeadComplaintService::class);

        // 75,00 EUR aufgeladen, 15,00 EUR fuer den Lead abgebucht.
        $this->assertSame(6000, $this->balanceCentsOf($buyer));

        $complaint = $service->file(
            $purchase,
            $buyer,
            LeadState::UNERREICHBAR,
            'Dreimal an verschiedenen Tagen angerufen, niemand erreichbar.',
        );

        $this->assertSame(ComplaintStatus::PENDING, $complaint->status);

        // Solange der Antrag laeuft, aendert sich nichts: Weder Zustand noch
        // Guthaben duerfen sich selbst bewilligen.
        $this->assertSame(LeadState::VERKAUFT, $purchase->lead->fresh()->lead_state);
        $this->assertSame(6000, $this->balanceCentsOf($buyer));

        $service->approve($complaint, $admin, 'Anrufprotokoll plausibel.');

        $complaint->refresh();

        $this->assertSame(ComplaintStatus::APPROVED, $complaint->status);
        $this->assertSame($admin->getKey(), $complaint->reviewed_by);

        // Zustand und Gutschrift zusammen -- ein Wechsel ohne Gutschrift waere
        // eine stille Enteignung.
        $this->assertSame(LeadState::UNERREICHBAR, $purchase->lead->fresh()->lead_state);
        $this->assertSame(7500, $this->balanceCentsOf($buyer));

        // Die Erstattung haengt am Kaufbeleg und dreht ihn auf `refunded`.
        $refund = WalletTransaction::query()
            ->where('type', WalletTransactionType::REFUND)->sole();

        $this->assertSame(1500, (int) $refund->amount_cents);
        $this->assertSame($purchase->getKey(), $refund->reference_id);
        $this->assertSame(PurchaseStatus::REFUNDED, $purchase->fresh()->status);

        // Und ein zweites Anerkennen bucht nicht noch einmal.
        try {
            $service->approve($complaint, $admin);
            $this->fail('Eine entschiedene Reklamation darf nicht erneut entschieden werden.');
        } catch (ComplaintNotAllowedException) {
            // erwartet
        }

        $this->assertSame(7500, $this->balanceCentsOf($buyer));
    }

    /**
     * Der Saldo eines Kaeufers in Cent -- ohne die geblockten Betraege, die
     * hier keine Rolle spielen: Der Kauf ist abgerechnet.
     */
    private function balanceCentsOf(Tenant $buyer): int
    {
        return (int) Wallet::forBuyer($buyer)->refresh()->balance_cents;
    }

    public function test_the_complaint_period_elapses_into_reached_but_never_over_an_open_request(): void
    {
        $deadline = (int) config('funnel.call.deadline_days');

        // Frisch gekauft: Die Frist laeuft noch, hier wird nichts abgeschlossen.
        [$fresh] = $this->purchase(boughtDaysAgo: 0);

        // Frist abgelaufen, niemand hat reklamiert -- Schweigen ist Zustimmung
        // zur Abrechnung.
        [$elapsed] = $this->purchase(boughtDaysAgo: $deadline + 1);

        // Frist abgelaufen, aber ein Antrag liegt in der Pruefung. Hier darf
        // die Uhr nicht entscheiden, was ein Mensch entscheiden soll. Der
        // Antrag geht fristgerecht ein und altert danach -- so passiert es auch
        // im Betrieb, wenn die Pruefung laenger dauert als die Frist.
        [$disputed, $disputedBuyer] = $this->purchase(boughtDaysAgo: 0);

        app(LeadComplaintService::class)->file(
            $disputed,
            $disputedBuyer,
            LeadState::UNGUELTIG,
            'Telefonnummer gehoert zu einer Praxis, nicht zu einem Tierhalter.',
        );

        $disputed->forceFill(['purchased_at' => now()->subDays($deadline + 1)])->save();

        $settled = app(LeadComplaintService::class)->settleElapsed();

        $this->assertSame(1, $settled);

        $this->assertSame(LeadState::VERKAUFT, $fresh->lead->fresh()->lead_state);
        $this->assertSame(LeadState::ERREICHT, $elapsed->lead->fresh()->lead_state);
        $this->assertSame(LeadState::VERKAUFT, $disputed->lead->fresh()->lead_state);

        // Ein zweiter Lauf schliesst nichts doppelt ab.
        $this->assertSame(0, app(LeadComplaintService::class)->settleElapsed());
    }

    public function test_a_complaint_after_the_deadline_is_refused(): void
    {
        $deadline = (int) config('funnel.call.deadline_days');

        [$purchase, $buyer] = $this->purchase(boughtDaysAgo: $deadline + 1);

        $this->expectException(ComplaintNotAllowedException::class);

        app(LeadComplaintService::class)->file($purchase, $buyer, LeadState::UNERREICHBAR, 'Zu spaet.');
    }
}
