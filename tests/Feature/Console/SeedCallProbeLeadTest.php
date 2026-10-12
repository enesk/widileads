<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Constants\LeadContactStatus;
use App\Constants\LeadState;
use App\Constants\PurchaseStatus;
use App\Constants\TenantType;
use App\Constants\WalletTransactionType;
use App\Models\BuyerRegistration;
use App\Models\CallerId;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\LeadStateLog;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTest;

/**
 * Ticket #89/#91: `leads:call-probe` legt einen Uebungs-Lead an und baut ihn
 * ueber den Fachweg zurueck.
 *
 * Geprueft wird genau das, was der Testumfang verlangt: die Geldseite und
 * `lead_state`. Der Uebungskauf laeuft ueber die echte Reservierung -- bliebe
 * nach dem Rueckbau ein Cent geblockt, waere das Guthaben des Betreibers
 * dauerhaft falsch und `wallet:verify` dauerhaft rot.
 */
class SeedCallProbeLeadTest extends FeatureTest
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();

        config(['twilio.from_number' => '+493012345678']);
    }

    public function test_it_creates_a_practice_lead_and_gives_the_reservation_back_on_teardown(): void
    {
        $operator = Tenant::factory()->create([
            'type' => TenantType::OPERATOR,
            'lead_price_cents' => 1500,
        ]);

        $funnel = Funnel::factory()->published()->forTenant($operator)->create();

        $buyer = BuyerRegistration::factory()->approved()->create()->tenant->fresh();
        $user = $this->createUser($buyer, [], ['email' => 'probe@widimedia.com']);

        CallerId::factory()->verified()->create([
            'tenant_id' => $buyer->getKey(),
            'user_id' => $user->getKey(),
            'phone_number' => '+4930111222333',
        ]);

        User::factory()->create(['is_admin' => true]);

        app(WalletService::class)->post(
            wallet: Wallet::forBuyer($buyer),
            type: WalletTransactionType::TOPUP,
            amountCents: 5000,
            description: 'Aufladung',
        );

        $before = Wallet::forBuyer($buyer);
        $balanceBefore = (int) $before->balance_cents;
        $reservedBefore = (int) $before->reserved_cents;

        $options = [
            '--to' => '+4915112345678',
            '--buyer' => $user->email,
            '--funnel' => (string) $funnel->getKey(),
        ];

        // Der Trockenlauf prueft und schreibt nicht.
        $this->artisan('leads:call-probe', $options + ['--dry-run' => true])->assertExitCode(0);
        $this->assertSame(0, $this->probeLeads()->count());

        $this->artisan('leads:call-probe', $options)->assertExitCode(0);

        /** @var Lead $lead */
        $lead = $this->probeLeads()->sole();

        $this->assertSame(LeadState::VERKAUFT, $lead->lead_state);
        $this->assertSame(LeadContactStatus::OPEN, $lead->contact_status);
        $this->assertSame('+4915112345678', $lead->phone_e164);

        /** @var LeadPurchase $purchase */
        $purchase = LeadPurchase::query()->where('lead_id', $lead->getKey())->sole();

        $this->assertSame(PurchaseStatus::RESERVED, $purchase->status);
        $this->assertSame((int) $user->getKey(), (int) $purchase->purchased_by_user_id);
        $this->assertStringContainsString('Ticket #89', (string) $purchase->buyer_notes);

        // Geldseite nach dem Kauf: der Kaufpreis ist geblockt, der Saldo
        // unberuehrt.
        $afterPurchase = Wallet::forBuyer($buyer);
        $this->assertSame($balanceBefore, (int) $afterPurchase->balance_cents);
        $this->assertSame($reservedBefore + 1500, (int) $afterPurchase->reserved_cents);

        // Ein zweiter Aufruf legt keinen zweiten Uebungs-Lead an.
        $this->artisan('leads:call-probe', $options)->assertExitCode(1);
        $this->assertSame(1, $this->probeLeads()->count());

        $this->artisan('leads:call-probe', ['--teardown' => true])->assertExitCode(0);

        $purchase->refresh();
        $lead->refresh();

        $this->assertSame(PurchaseStatus::RELEASED, $purchase->status);
        $this->assertSame(LeadState::UNERREICHBAR, $lead->lead_state);
        $this->assertSame(LeadContactStatus::UNREACHABLE, $lead->contact_status);

        // Die beiden Buchungen summieren auf null: Saldo und geblockter Betrag
        // stehen wieder wie vor dem Lauf.
        $afterTeardown = Wallet::forBuyer($buyer);
        $this->assertSame($balanceBefore, (int) $afterTeardown->balance_cents);
        $this->assertSame($reservedBefore, (int) $afterTeardown->reserved_cents);

        // Und die Klammern der Geldseite bleiben glatt -- genau das ist der
        // Grund, warum der Rueckbau nichts von Hand bucht.
        $this->artisan('wallet:verify')->assertExitCode(0);

        // Die Pflichtbegruendung steht im Zustandsprotokoll.
        $forced = LeadStateLog::query()
            ->where('lead_id', $lead->getKey())
            ->where('to_state', LeadState::UNERREICHBAR)
            ->sole();

        $this->assertStringContainsString('Ticket #89', (string) ($forced->meta['justification'] ?? ''));
    }

    /**
     * Ticket #93: Die Telefonsperre steht vor dem Anrufpanel. Ist
     * `phone_number_verified_at` offen, darf kein Uebungs-Lead entstehen --
     * sonst laeuft dessen Frist, waehrend der Kaeufer nicht hineinkommt.
     */
    public function test_it_refuses_when_the_calling_user_has_no_verified_phone(): void
    {
        $scene = $this->scene(callerIdNumber: '+4930111222333', phoneVerified: false);

        $this->artisan('leads:call-probe', $scene['options'])->assertExitCode(1);

        $this->assertSame(0, $this->probeLeads()->count());
        $this->assertSame(0, LeadPurchase::query()->withoutGlobalScopes()->count());
    }

    /**
     * Ticket #93: Ist die Rufnummernanzeige des Nutzers die Portal-Nummer,
     * waehlt CallService From gleich To und Twilio weist ab. Der Uebungs-Lead
     * darf dafuer nicht angelegt werden.
     */
    public function test_it_refuses_when_the_caller_id_equals_the_platform_number(): void
    {
        $scene = $this->scene(callerIdNumber: '+493012345678');

        $this->artisan('leads:call-probe', $scene['options'])->assertExitCode(1);

        $this->assertSame(0, $this->probeLeads()->count());
        $this->assertSame(0, LeadPurchase::query()->withoutGlobalScopes()->count());
    }

    /**
     * Der vollstaendige Aufbau eines gelingenden Laufs -- Verkaeufer, Funnel,
     * Kaeufer samt Guthaben, Admin fuer den Zwangswechsel.
     *
     * @return array{user: User, buyer: Tenant, options: array<string, string>}
     */
    private function scene(string $callerIdNumber, bool $phoneVerified = true): array
    {
        $operator = Tenant::factory()->create([
            'type' => TenantType::OPERATOR,
            'lead_price_cents' => 1500,
        ]);

        $funnel = Funnel::factory()->published()->forTenant($operator)->create();

        $buyer = BuyerRegistration::factory()->approved()->create()->tenant->fresh();
        $user = $this->createUser($buyer, [], [
            'email' => 'probe@widimedia.com',
            'phone_number_verified_at' => $phoneVerified ? now() : null,
        ]);

        CallerId::factory()->verified()->create([
            'tenant_id' => $buyer->getKey(),
            'user_id' => $user->getKey(),
            'phone_number' => $callerIdNumber,
        ]);

        User::factory()->create(['is_admin' => true]);

        app(WalletService::class)->post(
            wallet: Wallet::forBuyer($buyer),
            type: WalletTransactionType::TOPUP,
            amountCents: 5000,
            description: 'Aufladung',
        );

        return [
            'user' => $user,
            'buyer' => $buyer,
            'options' => [
                '--to' => '+4915112345678',
                '--buyer' => $user->email,
                '--funnel' => (string) $funnel->getKey(),
            ],
        ];
    }

    /**
     * @return Builder<Lead>
     */
    private function probeLeads()
    {
        return Lead::query()
            ->withoutGlobalScopes()
            ->where('utm_source', 'call-probe');
    }
}
