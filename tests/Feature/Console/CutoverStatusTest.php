<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Constants\LeadContactStatus;
use App\Constants\LeadState;
use App\Models\Lead;
use App\Models\LeadPurchase;
use Tests\Feature\FeatureTest;

/**
 * Ticket #94: `leads:cutover-status` ersetzt die beiden `tinker`-Blocks der
 * Betreiber-Sitzung.
 *
 * Geprueft wird, was im Fehlerfall Geld kostet: das fruehste zulaessige Datum
 * (Punkt 4) und der Trockenlauf (Punkt 5). Ein Cutover einen Tag zu frueh
 * rechnet Leads ab, zu denen kein Anrufversuch existiert -- der Trockenlauf
 * muss dieselbe Menge und dieselbe Summe nennen wie der spaetere 03:00-Lauf.
 */
class CutoverStatusTest extends FeatureTest
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('lead_calls.enforce_from', '');
    }

    public function test_it_names_the_earliest_allowed_date_one_day_after_the_last_open_reservation(): void
    {
        $this->reservedLead('2026-10-11 23:15:33', 1500);
        $this->reservedLead('2026-10-09 08:00:00', 1500);

        $this->artisan('leads:cutover-status', ['--skip-setup-check' => true])
            ->expectsOutputToContain('2026-10-12')
            ->assertFailed();
    }

    public function test_dry_run_counts_leads_purchases_and_money_for_the_given_date(): void
    {
        $this->reservedLead('2026-10-11 23:15:33', 1500);
        $this->reservedLead('2026-10-11 17:24:44', 1500);
        // Nach dem Cutover ausgeliefert -- vom Regelwerk erfasst, aber im
        // Trockenlauf auf den 11. nicht enthalten.
        $this->reservedLead('2026-10-13 09:00:00', 2000);

        $this->artisan('leads:cutover-status', $this->runOptions('2026-10-11'))
            ->expectsOutputToContain('Leads: 2')
            ->expectsOutputToContain('Kaufbelege in Reservierung: 2')
            ->expectsOutputToContain('Geldsumme: 30,00 EUR')
            ->assertFailed();

        // Ein Tag spaeter: dieselbe Abfrage, kein Treffer -- Carbon::parse()
        // schneidet auf 00:00 Uhr.
        $this->artisan('leads:cutover-status', $this->runOptions('2026-10-12'))
            ->expectsOutputToContain('Leads: 0')
            ->expectsOutputToContain('Geldsumme: 0,00 EUR')
            ->assertFailed();
    }

    public function test_dry_run_leaves_out_leads_in_a_final_state(): void
    {
        $this->reservedLead('2026-10-11 20:00:00', 1500, LeadState::UNERREICHBAR);

        $this->artisan('leads:cutover-status', $this->runOptions('2026-10-11'))
            ->expectsOutputToContain('Leads: 0')
            ->expectsOutputToContain('Geldsumme: 0,00 EUR')
            ->assertFailed();
    }

    /**
     * Stichtag nach Fristablauf: so rechnet der Betreiber den ersten Lauf
     * nach, der die Leads tatsaechlich greifen wuerde.
     *
     * @return array<string, string|bool>
     */
    private function runOptions(string $date): array
    {
        return ['--skip-setup-check' => true, '--date' => $date, '--at' => '2026-10-19 03:00:00'];
    }

    private function reservedLead(string $deliveredAt, int $priceCents, LeadState $state = LeadState::VERKAUFT): Lead
    {
        /** @var Lead $lead */
        $lead = Lead::factory()->inState($state)->create([
            'tenant_id' => $this->createTenant()->id,
            'contact_status' => LeadContactStatus::OPEN,
            'delivered_at' => $deliveredAt,
            'deadline_at' => now()->parse($deliveredAt)->addDays(7),
        ]);

        LeadPurchase::factory()->reserved()->create([
            'lead_id' => $lead->getKey(),
            'price_cents' => $priceCents,
        ]);

        return $lead;
    }
}
