<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\FunnelStatus;
use App\Constants\LeadState;
use App\Constants\TenantType;
use App\Filament\Dashboard\Pages\PurchasedLeads;
use App\Models\BuyerRegistration;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * FB-057: "Meine Leads".
 *
 * Zwei Zusagen: Ein Kaeufer sieht ausschliesslich seine eigenen Kaeufe, und die
 * Kontaktdaten stehen dort im Klartext -- aber nur dort. Der Kaufbeleg gehoert
 * dem Kaeufer, waehrend der Lead dem Betreiber gehoert; eine Verwechslung an
 * dieser Stelle zeigte einem Kaeufer die Kaeufe eines anderen.
 */
class PurchasedLeadsTest extends FeatureTest
{
    private const EMAIL = 'mara.lindqvist@example.com';

    private const PHONE = '+493012345678';

    private function purchasedLead(Tenant $buyer): LeadPurchase
    {
        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);

        $funnel = Funnel::factory()->create([
            'tenant_id' => $operator->getKey(),
            'name' => 'Pfotencheck',
            'status' => FunnelStatus::PUBLISHED,
        ]);

        $lead = Lead::factory()->inState(LeadState::VERKAUFT)->create([
            'tenant_id' => $operator->getKey(),
            'funnel_id' => $funnel->getKey(),
            'score' => 12,
            'email_normalized' => self::EMAIL,
            'phone_e164' => self::PHONE,
        ]);

        foreach (['tierart' => 'hund', 'vorname' => 'Mara', 'nachname' => 'Lindqvist',
            'email' => self::EMAIL, 'telefon' => self::PHONE, 'plz' => '76131'] as $key => $value) {
            LeadAnswer::query()->create(['lead_id' => $lead->getKey(), 'field_key' => $key, 'value' => $value]);
        }

        return LeadPurchase::factory()->create([
            'lead_id' => $lead->getKey(),
            'buyer_tenant_id' => $buyer->getKey(),
        ]);
    }

    /**
     * @return array{Tenant, User}
     */
    private function buyer(): array
    {
        $tenant = BuyerRegistration::factory()->approved()->create()->tenant->fresh();
        $user = User::factory()->create();
        $tenant->users()->attach($user);

        return [$tenant, $user];
    }

    public function test_a_buyer_sees_only_the_leads_the_own_workspace_bought(): void
    {
        [$mine, $myUser] = $this->buyer();
        [$theirs] = $this->buyer();

        $ownPurchase = $this->purchasedLead($mine);
        $foreignPurchase = $this->purchasedLead($theirs);

        $this->actingAs($myUser);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($mine);

        $html = Livewire::actingAs($myUser)->test(PurchasedLeads::class)->assertSuccessful()->html();

        // Der eigene Kauf ist da -- samt Klartext, denn der Kaufbeleg existiert.
        $this->assertStringContainsString('Pfotencheck', $html);
        $this->assertStringContainsString(self::EMAIL, $html);
        $this->assertStringContainsString(self::PHONE, $html);

        // Genau ein Eintrag: der fremde Kauf taucht nicht auf.
        $this->assertSame(1, substr_count($html, 'Pfotencheck'));

        // Die Tabelle fuehrt nur den eigenen Kauf -- und weil eine
        // Tabellenaktion ihren Datensatz aus genau dieser Abfrage holt, ist der
        // fremde Kauf auch fuer die Rueckmeldung nicht erreichbar.
        Livewire::actingAs($myUser)->test(PurchasedLeads::class)
            ->assertCanSeeTableRecords([$ownPurchase])
            ->assertCanNotSeeTableRecords([$foreignPurchase]);

        $this->assertNull($foreignPurchase->fresh()->buyer_feedback);

        Livewire::actingAs($myUser)->test(PurchasedLeads::class)
            ->callAction(TestAction::make('feedback')->table($ownPurchase), [
                'buyer_feedback' => 'interested',
            ]);

        $this->assertNotNull($ownPurchase->fresh()->buyer_feedback);
    }

    public function test_the_csv_export_contains_only_the_own_purchases(): void
    {
        [$mine, $myUser] = $this->buyer();
        [$theirs] = $this->buyer();

        $this->purchasedLead($mine);
        $this->purchasedLead($theirs);

        $this->actingAs($myUser);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($mine);

        $csv = $this->captureDownload($myUser);

        // Eine Kopfzeile und genau eine Datenzeile.
        $lines = array_values(array_filter(explode("\n", trim($csv))));

        $this->assertCount(2, $lines);
        $this->assertStringContainsString(self::EMAIL, $csv);

        // Der Export personenbezogener Daten steht im Audit-Log.
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $mine->getKey(),
            'action' => 'data.exported',
        ]);
    }

    /**
     * Der CSV-Inhalt, wie ihn der Browser bekaeme.
     */
    private function captureDownload(User $user): string
    {
        $response = Livewire::actingAs($user)->test(PurchasedLeads::class)
            ->instance()
            ->exportCsv();

        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }
}
