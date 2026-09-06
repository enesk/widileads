<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\AuditAction;
use App\Constants\LeadState;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Services\LeadDataRequestService;
use Tests\Feature\FeatureTest;

/**
 * FB-038: Auskunft und Loeschersuchen nach DSGVO.
 *
 * Geprueft wird, was teuer waere, wenn es falsch ist: dass die Loeschung den
 * Personenbezug entfernt, ohne Preis und Zustand mitzunehmen, und dass beide
 * Vorgaenge nachvollziehbar im Audit-Log stehen. Das Format der Auskunft ist
 * bewusst nicht festgeschrieben.
 */
class LeadDataRequestTest extends FeatureTest
{
    private function service(): LeadDataRequestService
    {
        return app(LeadDataRequestService::class);
    }

    private function settledLead(): Lead
    {
        return Lead::factory()->inState(LeadState::ERREICHT)->create([
            'tenant_id' => $this->createTenant()->id,
            'settled_price' => 15.00,
            'settled_at' => now()->subDay(),
        ]);
    }

    public function test_an_erasure_request_keeps_price_and_state_and_is_audited(): void
    {
        $lead = $this->settledLead();
        $admin = $this->createAdminUser();

        $this->assertTrue($this->service()->eraseLead($lead, $admin));

        $lead->refresh();
        $this->assertNotNull($lead->anonymized_at, 'Der Personenbezug muss entfernt sein.');
        $this->assertSame('15.00', $lead->settled_price, 'Der festgeschriebene Preis bleibt.');
        $this->assertSame(LeadState::ERREICHT, $lead->lead_state, 'Der Zustand bleibt.');
        $this->assertNotNull($lead->settled_at);

        $audit = AuditLog::query()->where('action', AuditAction::DATA_ERASED)->sole();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame($lead->tenant_id, $audit->tenant_id);
        $this->assertSame((string) $lead->id, $audit->subject_id);

        // Ein zweiter Aufruf aendert nichts und schreibt keinen zweiten Eintrag.
        $this->assertFalse($this->service()->eraseLead($lead, $admin));
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::DATA_ERASED)->count());
    }

    public function test_an_access_request_is_audited_and_returns_the_stored_record(): void
    {
        $lead = $this->settledLead();
        $admin = $this->createAdminUser();

        $export = $this->service()->exportLead($lead, $admin);

        $this->assertSame($lead->id, $export['lead']['id']);
        $this->assertArrayHasKey('state_log', $export);
        $this->assertArrayHasKey('answers', $export);

        $audit = AuditLog::query()->where('action', AuditAction::DATA_EXPORTED)->sole();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame((string) $lead->id, $audit->subject_id);
    }

    public function test_a_request_by_email_stays_empty_while_the_contact_column_is_missing(): void
    {
        $this->settledLead();
        $admin = $this->createAdminUser();

        // leads.email_normalized legt erst FB-031 an: bis dahin findet die
        // Suche nichts, statt zu brechen.
        $export = $this->service()->exportForEmail('Max.Mustermann@example.com', $admin);

        $this->assertSame('max.mustermann@example.com', $export['email'], 'Die Adresse wird normalisiert.');
        $this->assertSame(0, $export['lead_count']);
        $this->assertSame([], $export['leads']);

        $this->assertSame(0, $this->service()->eraseForEmail('max.mustermann@example.com', $admin));
        $this->assertSame(0, AuditLog::query()->whereIn('action', [
            AuditAction::DATA_EXPORTED,
            AuditAction::DATA_ERASED,
        ])->count(), 'Ohne betroffenen Lead gibt es nichts zu protokollieren.');
    }
}
