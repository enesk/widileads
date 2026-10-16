<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Portal\Onboarding;

use App\Livewire\Portal\Onboarding\BillingExplainer;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * Der 3-Schritte-Player "So wird abgerechnet" (Ticket #3).
 *
 * Geprueft wird, dass die drei Schritte vollstaendig im Markup stehen und ihre
 * Zahlen aus der Konfiguration kommen -- nicht aus fest eingetragenem Text.
 */
class BillingExplainerTest extends FeatureTest
{
    public function test_der_player_zeigt_alle_drei_schritte_mit_ihren_motivzustaenden(): void
    {
        $html = Livewire::test(BillingExplainer::class, ['priceCents' => 1890, 'balanceCents' => 5000])->html();

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

        $html = Livewire::test(BillingExplainer::class, ['priceCents' => 1890, 'balanceCents' => 5000])->html();

        $this->assertStringContainsString('42 Sekunden', $html);
        $this->assertStringContainsString('5 Versuchen', $html);
    }

    public function test_die_betraege_stehen_formatiert_im_markup(): void
    {
        $html = Livewire::test(BillingExplainer::class, ['priceCents' => 1890, 'balanceCents' => 5000])->html();

        // Abbuchung im zweiten, Rueckbuchung im dritten Schritt, dazu der
        // Anfangsstand der Guthabenzahl.
        $this->assertStringContainsString('-18,90', $html);
        $this->assertStringContainsString('+18,90', $html);
        $this->assertStringContainsString('50,00', $html);
    }
}
