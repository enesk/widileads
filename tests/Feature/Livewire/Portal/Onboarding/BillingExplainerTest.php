<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Portal\Onboarding;

use App\Constants\PaymentMode;
use App\Livewire\Portal\Onboarding\BillingExplainer;
use App\Models\BuyerRegistration;
use App\Models\Tenant;
use App\Models\Wallet;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * Der 3-Schritte-Player "So wird abgerechnet" (Ticket #3).
 *
 * Geprueft wird nicht, dass bestimmte Saetze dastehen, sondern dass die
 * Aussagen zur Abrechnungsregel passen: Die Erklaerung war monatelang
 * unveraendert, waehrend sich die Abrechnung zweimal geaendert hat -- und
 * genau das faellt nur auf, wenn ein Test die Regel kennt.
 *
 * Drei Aussagen haengen daran:
 *
 *  1. Der Anfragende sieht die bestaetigte Nummer des Kaeufers, nicht die des
 *     Portals (CallService::bridgeInstruction, `callerId => caller_number`).
 *  2. Laeuft die Frist ohne die Versuche ab, wird der Lead berechnet
 *     (LeadResolutionReason::DEADLINE -> BILLABLE) -- der Screen darf nicht
 *     nur die kostenlose Haelfte erzaehlen.
 *  3. Bei Pay as you go geht nichts vom Guthaben ab: Preis plus Aufschlag
 *     kommen auf die Abrechnung.
 */
class BillingExplainerTest extends FeatureTest
{
    public function test_der_player_zeigt_alle_drei_schritte_mit_ihren_motivzustaenden(): void
    {
        $html = $this->render();

        // Jeder Schritt steht vollstaendig im HTML -- ohne Skript bleibt der
        // erste lesbar.
        foreach (['ringing', 'answered', 'missed'] as $state) {
            $this->assertStringContainsString('data-state="'.$state.'"', $html);
        }

        $this->assertStringContainsString('Schritt 1 von 3', $html);
        $this->assertStringContainsString('Schritt 3 von 3', $html);
    }

    public function test_die_zahlen_kommen_aus_der_konfiguration_und_nicht_aus_dem_text(): void
    {
        config([
            'lead_calls.answered_min_seconds' => 42,
            'lead_calls.unreachable_attempts' => 5,
        ]);

        $html = $this->render();

        $this->assertStringContainsString('42 Sekunden', $html);
        $this->assertStringContainsString('5 Versuchen', $html);
        // Auch der Freigabehinweis liest dieselbe Schwelle.
        $this->assertStringContainsString('5 Anrufe gezählt', $html);
    }

    public function test_der_erste_schritt_sagt_dass_der_anfragende_die_eigene_nummer_sieht(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('sieht dabei deine bestätigte Rufnummer', $html);

        // Die Gegenprobe ist der eigentliche Punkt: Bis Ticket #3 stand hier
        // das Gegenteil, und niemandem fiel es auf.
        $this->assertStringNotContainsString('bleibt dabei unsichtbar', $html);
        $this->assertStringNotContainsString('Portalnummer', $html);
    }

    public function test_der_dritte_schritt_nennt_auch_den_fristablauf(): void
    {
        $html = $this->render();

        // Nicht erreicht ist nur dann kostenlos, wenn die Versuche gemacht
        // wurden. Wer nichts tut, zahlt -- das muss dastehen.
        $this->assertStringContainsString('Frist ohne diese Versuche verstreichen', $html);
        $this->assertStringContainsString('wird er berechnet', $html);
    }

    public function test_bei_guthaben_geht_der_preis_vom_guthaben_ab(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('vom Guthaben ab', $html);
        $this->assertStringContainsString('-18,90', $html);
        $this->assertStringContainsString('+18,90', $html);
        // Der Anfangsstand der Zahl unter den Schritten.
        $this->assertStringContainsString('50,00', $html);
        $this->assertStringNotContainsString('Offener Betrag', $html);
    }

    public function test_bei_pay_as_you_go_kommt_der_preis_mit_aufschlag_auf_die_abrechnung(): void
    {
        $tenant = $this->postpaidBuyer(surchargePercent: 10.0);

        Filament::setTenant($tenant, isQuiet: true);

        $html = $this->render();

        // Kein Guthaben, sondern der offene Betrag -- und er waechst.
        $this->assertStringContainsString('Offener Betrag', $html);
        $this->assertStringNotContainsString('vom Guthaben ab', $html);

        // 18,90 EUR plus 10 Prozent = 20,79 EUR.
        $this->assertStringContainsString('10 % Aufschlag', $html);
        $this->assertStringContainsString('20,79', $html);
        $this->assertStringContainsString('+20,79', $html);

        // Zurueckgebucht wird nichts, was nie abgebucht wurde.
        $this->assertStringContainsString('kommt nicht auf die Abrechnung', $html);
    }

    private function render(): string
    {
        return Livewire::test(BillingExplainer::class, [
            'priceCents' => 1890,
            'balanceCents' => 5000,
        ])->html();
    }

    /**
     * Ein Kaeufer-Workspace mit Pay-as-you-go-Wallet und eigenem
     * Aufschlagsatz.
     */
    private function postpaidBuyer(float $surchargePercent): Tenant
    {
        $tenant = BuyerRegistration::factory()->approved()->create()->tenant->fresh();

        $wallet = Wallet::forBuyer($tenant);

        $wallet->forceFill([
            'payment_mode' => PaymentMode::POSTPAID,
            'surcharge_percent' => $surchargePercent,
        ])->save();

        return $tenant;
    }
}
