<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Constants\LeadState;
use App\Constants\PurchaseStatus;
use App\Constants\TenantType;
use App\Constants\WalletTransactionType;
use App\Mail\Wallet\WalletLedgerMismatch;
use App\Models\BuyerRegistration;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\FeatureTest;

/**
 * Zweite Klammer von `wallet:verify` (Ticket #81): Sie soll die verwaiste
 * Reservierung benennen, nicht nur ihre Summe melden.
 *
 * Drei Faelle, und alle drei sichern dasselbe ab: Ein Fehlalarm macht den
 * naechtlichen Lauf dauerhaft rot und damit wertlos. Deshalb steht neben den
 * beiden echten Befunden der Fall mit Gegenbuchung, der gruen bleiben muss.
 */
class VerifyWalletLedgerTest extends FeatureTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // Die Befundzeilen reisen in der Meldung an den Betreiber -- dort sind
        // sie einzeln pruefbar, waehrend die Konsolentabelle alles in eine
        // Ausgabe schreibt.
        Mail::fake();
        config(['app.support_email' => 'betrieb@widimedia.com']);
    }

    public function test_it_names_the_purchase_behind_a_reservation_without_a_counter_entry(): void
    {
        [$buyer, $purchase] = $this->aBuyerWithAReservedPurchase();

        // Der Beleg verschwindet, die Reservierung im Journal bleibt -- genau
        // der Befund aus #78.
        LeadPurchase::query()->whereKey($purchase->getKey())->delete();

        $this->artisan('wallet:verify')->assertExitCode(1);

        $detail = $this->findingAbout('Kaufbeleg #'.$purchase->getKey());

        $this->assertStringContainsString('15,00 EUR', $detail);
        $this->assertStringContainsString('existiert nicht mehr', $detail);

        // Ebene 2 nennt das Wallet, Ebene 3 den Beleg darunter.
        $this->assertStringContainsString(
            'Differenz 15,00 EUR',
            $this->findingAbout('Wallet #'.Wallet::forBuyer($buyer)->getKey()),
        );

        $this->assertSame(1500, Wallet::forBuyer($buyer)->reserved_cents);
    }

    public function test_the_orphan_counter_entry_closes_the_finding_without_a_purchase_reference(): void
    {
        [, $purchase] = $this->aBuyerWithAReservedPurchase();

        $wallet = Wallet::forBuyer($purchase->buyer);
        $purchaseId = (int) $purchase->getKey();

        LeadPurchase::query()->whereKey($purchaseId)->delete();

        // Gegenbuchung wie in #78: ohne Beleg, der Bezug steht nur im
        // Idempotenzschluessel.
        app(WalletService::class)->post(
            wallet: $wallet,
            type: WalletTransactionType::RELEASE,
            amountCents: -1500,
            description: 'Gegenbuchung einer belegfreien Reservierung',
            idempotencyKey: sprintf('release:lead_purchase:%d:orphan', $purchaseId),
        );

        $this->artisan('wallet:verify')
            ->doesntExpectOutputToContain('Kaufbeleg #'.$purchaseId)
            ->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    public function test_a_captured_purchase_without_a_release_stays_a_finding(): void
    {
        [, $purchase] = $this->aBuyerWithAReservedPurchase();

        $wallet = Wallet::forBuyer($purchase->buyer);

        // Abbuchung ohne Freigabe: `capture` bewegt den Saldo, nicht den
        // reservierten Betrag -- das Guthaben bleibt blockiert.
        app(WalletService::class)->post(
            wallet: $wallet,
            type: WalletTransactionType::CAPTURE,
            amountCents: -1500,
            description: 'Abbuchung',
            reference: $purchase,
        );

        $purchase->forceFill(['status' => PurchaseStatus::CAPTURED->value])->save();

        $this->artisan('wallet:verify')->assertExitCode(1);

        $detail = $this->findingAbout('Kaufbeleg #'.$purchase->getKey());

        $this->assertStringContainsString('captured', $detail);
        $this->assertStringContainsString('15,00 EUR', $detail);
    }

    /**
     * Der Befund zu einem Betroffenen, so wie er in der Meldung an den
     * Betreiber steht -- die Konsolentabelle traegt dieselben Zeichenketten.
     */
    private function findingAbout(string $subject): string
    {
        $mails = Mail::queued(WalletLedgerMismatch::class);

        $this->assertCount(1, $mails, 'Es wurde keine Meldung an den Betreiber verschickt.');

        /** @var WalletLedgerMismatch $mail */
        $mail = $mails->first();

        $this->assertTrue($mail->reservationHint, 'Der Hinweis zum Gegenbuchen fehlt.');

        foreach ($mail->findings as $finding) {
            if (str_contains($finding['subject'], $subject)) {
                return $finding['check'].' -- '.$finding['detail'];
            }
        }

        $this->fail(sprintf('Kein Befund zu "%s" in der Meldung.', $subject));
    }

    /**
     * Wie in ResetWalletLedgerTest: ein Kaeufer mit Guthaben, einem Leadkauf im
     * Stand `reserved` und der passenden Reservierung im Journal.
     *
     * @return array{Tenant, LeadPurchase}
     */
    private function aBuyerWithAReservedPurchase(): array
    {
        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $buyer = BuyerRegistration::factory()->approved()->create()->tenant->fresh();

        $wallets = app(WalletService::class);
        $wallet = Wallet::forBuyer($buyer);

        $wallets->post($wallet, WalletTransactionType::TOPUP, 15000, 'Aufladung');

        $lead = Lead::factory()->inState(LeadState::VERKAUFT)->create([
            'tenant_id' => $operator->getKey(),
            'score' => 10,
        ]);

        $purchase = LeadPurchase::factory()->create([
            'lead_id' => $lead->getKey(),
            'buyer_tenant_id' => $buyer->getKey(),
            'seller_tenant_id' => $operator->getKey(),
            'price_cents' => 1500,
            'status' => PurchaseStatus::RESERVED,
            'reserved_at' => now(),
            'purchased_at' => now(),
        ]);

        $wallets->post(
            wallet: $wallet,
            type: WalletTransactionType::RESERVE,
            amountCents: 1500,
            description: 'Reservierung',
            reference: $purchase,
        );

        return [$buyer, $purchase];
    }
}
