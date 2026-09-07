<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\LeadState;
use App\Constants\TenancyPermissionConstants;
use App\Constants\TenantType;
use App\Filament\Dashboard\Resources\Leads\Pages\ListLeads;
use App\Filament\Dashboard\Resources\Leads\Pages\ViewLead;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LeadListQuery;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * FB-034: Lead-Liste und Lead-Detail des Betreibers.
 *
 * Zwei Dinge sind hier teuer, wenn sie falsch sind: ein Filter, der zu viel
 * durchlaesst (dann sieht ein Mitglied Daten, die es nicht sehen soll), und die
 * Mandantentrennung (dann sieht ein Betreiber die Leads eines anderen).
 */
class LeadListTest extends FeatureTest
{
    private function operator(): Tenant
    {
        return Tenant::factory()->create(['type' => TenantType::OPERATOR]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $answers
     */
    private function lead(Tenant $tenant, array $attributes = [], array $answers = []): Lead
    {
        $lead = Lead::factory()->inState(LeadState::VERFUEGBAR)->create([
            'tenant_id' => $tenant->id,
            'score' => 5,
            'postal_code' => '76131',
            'email_normalized' => 'mara@example.com',
            'phone_e164' => '+493012345678',
            ...$attributes,
        ]);

        foreach ($answers + ['vorname' => 'Mara', 'nachname' => 'Lindqvist'] as $fieldKey => $value) {
            LeadAnswer::query()->create([
                'lead_id' => $lead->id,
                'field_key' => $fieldKey,
                'value' => $value,
            ]);
        }

        return $lead->fresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<int>
     */
    private function idsFor(Tenant $tenant, User $viewer, array $filters): array
    {
        return app(LeadListQuery::class)->build($tenant, $viewer, $filters)->pluck('id')->all();
    }

    public function test_the_filters_narrow_the_list_the_way_the_ticket_describes(): void
    {
        $tenant = $this->operator();
        $admin = $this->createUser($tenant, [TenancyPermissionConstants::PERMISSION_SEARCH_LEAD_CONTACTS]);

        $funnelA = Funnel::factory()->create(['tenant_id' => $tenant->id]);
        $funnelB = Funnel::factory()->create(['tenant_id' => $tenant->id]);

        $wanted = $this->lead($tenant, [
            'funnel_id' => $funnelA->id,
            'score' => 8,
            'postal_code' => '76131',
            'created_at' => now()->subDay(),
        ]);

        $otherFunnel = $this->lead($tenant, ['funnel_id' => $funnelB->id, 'score' => 8, 'postal_code' => '76131']);
        $otherState = $this->lead($tenant, ['funnel_id' => $funnelA->id, 'score' => 8, 'postal_code' => '76131', 'lead_state' => LeadState::UNGUELTIG]);
        $otherPostal = $this->lead($tenant, ['funnel_id' => $funnelA->id, 'score' => 8, 'postal_code' => '10115']);
        $lowScore = $this->lead($tenant, ['funnel_id' => $funnelA->id, 'score' => 2, 'postal_code' => '76131']);
        $tooOld = $this->lead($tenant, ['funnel_id' => $funnelA->id, 'score' => 8, 'postal_code' => '76131', 'created_at' => now()->subDays(30)]);

        $found = $this->idsFor($tenant, $admin, [
            'funnel_id' => $funnelA->id,
            'lead_state' => LeadState::VERFUEGBAR->value,
            'postal_prefix' => '76',
            'score_min' => 5,
            'score_max' => 10,
            'from' => now()->subDays(3)->toDateString(),
            'until' => now()->toDateString(),
        ]);

        $this->assertSame([$wanted->id], $found);

        foreach ([$otherFunnel, $otherState, $otherPostal, $lowScore, $tooOld] as $excluded) {
            $this->assertNotContains($excluded->id, $found);
        }
    }

    public function test_only_a_manager_searches_across_contact_data(): void
    {
        $tenant = $this->operator();
        $admin = $this->createUser($tenant, [TenancyPermissionConstants::PERMISSION_SEARCH_LEAD_CONTACTS]);
        $member = $this->createUser($tenant);

        $byName = $this->lead($tenant, ['email_normalized' => 'irgendwer@example.com']);
        $byEmail = $this->lead($tenant, ['email_normalized' => 'gesucht@example.com'], ['vorname' => 'Jonas', 'nachname' => 'Weber']);

        // Der Verwalter findet den Lead ueber die Adresse ...
        $this->assertSame([$byEmail->id], $this->idsFor($tenant, $admin, ['search' => 'gesucht@example.com']));

        // ... das Mitglied nicht.
        $this->assertSame([], $this->idsFor($tenant, $member, ['search' => 'gesucht@example.com']));

        // Ueber den Namen finden beide.
        $this->assertSame([$byName->id], $this->idsFor($tenant, $member, ['search' => 'Lindqvist']));
        $this->assertSame([$byName->id], $this->idsFor($tenant, $admin, ['search' => 'Lindqvist']));
    }

    public function test_a_lead_that_sits_in_the_marketplace_too_long_is_marked(): void
    {
        config()->set('funnel.lead.stale_after_days', 3);

        $tenant = $this->operator();
        $query = app(LeadListQuery::class);

        $fresh = $this->lead($tenant, ['created_at' => now()->subDay()]);
        $stale = $this->lead($tenant, ['created_at' => now()->subDays(4)]);
        $soldAndOld = $this->lead($tenant, ['created_at' => now()->subDays(4), 'lead_state' => LeadState::VERKAUFT]);

        $this->assertFalse($query->isStale($fresh));
        $this->assertTrue($query->isStale($stale));
        $this->assertFalse($query->isStale($soldAndOld), 'Nur wer kaufbar ist, kann liegen bleiben.');
    }

    /**
     * Die Liste muss auch bei vielen Leads schnell bleiben.
     *
     * Das Ticket nennt 500 ms bei 50.000 Leads. Eine Millisekunden-Zusicherung
     * waere in der CI allerdings unzuverlaessig -- sie sagt mehr ueber die
     * Auslastung des Rechners als ueber die Abfrage. Geprueft wird deshalb, was
     * die Laufzeit tatsaechlich bestimmt: dass die Abfrage einen Index benutzt
     * statt die Tabelle zu lesen, und dass die Anzeige einer Seite nicht mit der
     * Zeilenzahl waechst.
     */
    public function test_the_list_stays_indexed_and_free_of_n_plus_one(): void
    {
        $tenant = $this->operator();
        $admin = $this->createUser($tenant, [TenancyPermissionConstants::PERMISSION_SEARCH_LEAD_CONTACTS]);
        $funnel = Funnel::factory()->create(['tenant_id' => $tenant->id]);

        $this->seedLeads($tenant, $funnel, 5000);

        // Der Filter, den die Oberflaeche als Erstes anbietet.
        $sql = app(LeadListQuery::class)
            ->build($tenant, $admin, ['lead_state' => LeadState::VERFUEGBAR->value]);

        $plan = DB::select('EXPLAIN '.$sql->toSql(), $sql->getBindings())[0];

        $this->assertNotNull($plan->key, 'Die Lead-Liste darf nicht ueber die ganze Tabelle laufen.');
        $this->assertNotSame('ALL', $plan->type, "Zugriffsart war {$plan->type} statt eines Indexzugriffs.");

        // Eine Seite kostet eine feste Zahl Abfragen -- unabhaengig davon, wie
        // viele Zeilen sie zeigt.
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant);

        Livewire::test(ListLeads::class)->assertOk();

        $this->assertLessThan(15, $queries, "Die Seite hat {$queries} Abfragen ausgeloest -- das riecht nach N+1.");
    }

    private function seedLeads(Tenant $tenant, Funnel $funnel, int $count): void
    {
        $now = now();

        foreach (array_chunk(range(1, $count), 500) as $chunk) {
            $rows = [];

            foreach ($chunk as $index) {
                $rows[] = [
                    // Massen-Insert am Model vorbei: die Kennung aus FB-030d
                    // muss hier von Hand mitkommen.
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'funnel_id' => $funnel->id,
                    'lead_state' => LeadState::VERFUEGBAR->value,
                    'score' => $index % 20,
                    'postal_code' => str_pad((string) ($index % 99999), 5, '0', STR_PAD_LEFT),
                    'created_at' => $now->copy()->subMinutes($index),
                    'updated_at' => $now,
                ];
            }

            DB::table('leads')->insert($rows);
        }
    }

    public function test_neither_list_nor_detail_reaches_leads_of_another_tenant(): void
    {
        $mine = $this->operator();
        $foreign = $this->operator();

        $ownLead = $this->lead($mine);
        $foreignLead = $this->lead($foreign);

        $admin = $this->createUser($mine, [TenancyPermissionConstants::PERMISSION_SEARCH_LEAD_CONTACTS]);

        $this->assertSame([$ownLead->id], $this->idsFor($mine, $admin, []));

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($mine);

        // Die Tabelle zeigt genau den eigenen Lead -- geprueft ueber die
        // Datensaetze, nicht ueber den HTML-Text: eine nackte Kennung wie "8"
        // steht auch in einer Punktzahl oder einer Seitenzahl.
        Livewire::test(ListLeads::class)
            ->assertCanSeeTableRecords([$ownLead])
            ->assertCanNotSeeTableRecords([$foreignLead]);

        // Auch mit geratener Kennung in der Adresszeile: Die Detailseite
        // findet ihn nicht, weil getEloquentQuery auf den Workspace
        // einschraenkt.
        $this->expectException(ModelNotFoundException::class);

        Livewire::test(ViewLead::class, ['record' => $foreignLead->getKey()]);
    }
}
