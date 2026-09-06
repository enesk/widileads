<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\AuditAction;
use App\Exceptions\AuditLogIsImmutableException;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use Filament\Events\TenantSet;
use Illuminate\Support\Facades\DB;
use Tests\Feature\FeatureTest;

/**
 * FB-005: Audit-Log-Dienst.
 *
 * Deckt die Muss-Anforderungen des Tickets ab: unveraenderliche Eintraege,
 * gehashte IP-Adressen, gesetzte Pflichtereignisse (Login, Mandantenwechsel,
 * Rollenaenderung) und die vorbereiteten Aktionen fuer spaetere Tickets.
 */
class AuditLogTest extends FeatureTest
{
    private function auditLogger(): AuditLogger
    {
        return app(AuditLogger::class);
    }

    /**
     * Haengt eine Session an den aktuellen Request, wie es im Web-Request der
     * Fall waere. Der Mandantenwechsel merkt sich darin den zuletzt
     * protokollierten Mandanten.
     */
    private function startSessionForRequest(): void
    {
        $session = app('session')->driver();
        $session->start();

        request()->setLaravelSession($session);
    }

    private function clearAuditLog(): void
    {
        // Bewusst ueber die Query-Builder-Fassade: das Model laesst Loeschen nicht zu.
        DB::table('audit_logs')->delete();
    }

    public function test_log_writes_entry_with_actor_tenant_and_subject(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);
        $this->actingAs($user);
        $this->clearAuditLog();

        $entry = $this->auditLogger()->log(
            AuditAction::DATA_EXPORTED,
            subject: $tenant,
            payload: ['format' => 'csv'],
            tenant: $tenant,
        );

        $this->assertSame(AuditAction::DATA_EXPORTED, $entry->action);
        $this->assertSame($tenant->id, $entry->tenant_id);
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame(Tenant::class, $entry->subject_type);
        $this->assertSame((string) $tenant->id, $entry->subject_id);
        $this->assertSame(['format' => 'csv'], $entry->payload);
        $this->assertNotNull($entry->created_at);
        $this->assertNull($entry->getAttribute('updated_at'));
    }

    public function test_ip_address_is_only_stored_as_salted_sha256_hash(): void
    {
        config()->set('funnel.audit.ip_salt', 'pfeffer');
        $this->clearAuditLog();

        $entry = $this->auditLogger()->log(AuditAction::USER_LOGGED_IN, ipAddress: '203.0.113.42');

        $this->assertSame(hash('sha256', 'pfeffer|203.0.113.42'), $entry->ip_hash);
        $this->assertSame(64, strlen((string) $entry->ip_hash));

        $rawRow = (array) DB::table('audit_logs')->where('id', $entry->id)->first();

        $this->assertStringNotContainsString(
            '203.0.113.42',
            (string) json_encode($rawRow),
            'Die Roh-IP darf in keiner Spalte des Audit-Logs auftauchen.',
        );
    }

    public function test_ip_hash_falls_back_to_the_app_key_as_salt(): void
    {
        config()->set('funnel.audit.ip_salt', '');

        $entry = $this->auditLogger()->log(AuditAction::USER_LOGGED_IN, ipAddress: '198.51.100.7');

        $this->assertSame(hash('sha256', config('app.key').'|198.51.100.7'), $entry->ip_hash);
    }

    public function test_entry_without_ip_address_has_no_hash(): void
    {
        $entry = $this->auditLogger()->log(AuditAction::USER_LOGGED_IN, ipAddress: null);

        $this->assertNull($entry->ip_hash);
    }

    public function test_sensitive_payload_values_are_redacted(): void
    {
        $entry = $this->auditLogger()->log(AuditAction::API_TOKEN_CREATED, payload: [
            'name' => 'Deploy-Token',
            'plain_text_token' => 'geheim-123',
            'context' => [
                'password' => 'geheim',
                'ip' => '203.0.113.42',
                'abilities' => ['funnels:read'],
            ],
        ]);

        $this->assertSame([
            'name' => 'Deploy-Token',
            'plain_text_token' => AuditLogger::REDACTED,
            'context' => [
                'password' => AuditLogger::REDACTED,
                'ip' => AuditLogger::REDACTED,
                'abilities' => ['funnels:read'],
            ],
        ], $entry->payload);
    }

    public function test_entry_cannot_be_updated(): void
    {
        $entry = $this->auditLogger()->log(AuditAction::USER_LOGGED_IN);

        $this->assertThrows(
            fn () => $entry->update(['action' => AuditAction::LEAD_PURCHASED->value]),
            AuditLogIsImmutableException::class,
        );

        $this->assertSame(
            AuditAction::USER_LOGGED_IN->value,
            DB::table('audit_logs')->where('id', $entry->id)->value('action'),
        );
    }

    public function test_entry_cannot_be_saved_with_changed_attributes(): void
    {
        $entry = $this->auditLogger()->log(AuditAction::USER_LOGGED_IN);
        $entry->action = AuditAction::LEAD_PURCHASED;

        $this->assertThrows(fn () => $entry->save(), AuditLogIsImmutableException::class);

        $this->assertSame(
            AuditAction::USER_LOGGED_IN->value,
            DB::table('audit_logs')->where('id', $entry->id)->value('action'),
        );
    }

    public function test_entry_cannot_be_deleted(): void
    {
        $entry = $this->auditLogger()->log(AuditAction::USER_LOGGED_IN);

        $this->assertThrows(fn () => $entry->delete(), AuditLogIsImmutableException::class);

        $this->assertDatabaseHas('audit_logs', ['id' => $entry->id]);
    }

    public function test_mass_update_and_mass_delete_are_blocked(): void
    {
        $entry = $this->auditLogger()->log(AuditAction::USER_LOGGED_IN);

        $this->assertThrows(
            fn () => AuditLog::query()->where('id', $entry->id)->update(['ip_hash' => null]),
            AuditLogIsImmutableException::class,
        );

        $this->assertThrows(
            fn () => AuditLog::query()->where('id', $entry->id)->delete(),
            AuditLogIsImmutableException::class,
        );

        $this->assertThrows(fn () => AuditLog::query()->truncate(), AuditLogIsImmutableException::class);

        $this->assertDatabaseHas('audit_logs', ['id' => $entry->id]);
    }

    public function test_successful_login_is_audited(): void
    {
        $user = $this->createUser();
        $this->clearAuditLog();

        auth()->login($user);

        $entry = AuditLog::query()->where('action', AuditAction::USER_LOGGED_IN->value)->sole();

        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame(User::class, $entry->subject_type);
        $this->assertSame((string) $user->id, $entry->subject_id);
    }

    public function test_tenant_switch_is_audited_only_when_the_tenant_changes(): void
    {
        $firstTenant = $this->createTenant();
        $secondTenant = $this->createTenant();
        $user = $this->createUser($firstTenant);
        $this->actingAs($user);
        $this->startSessionForRequest();
        $this->clearAuditLog();

        TenantSet::dispatch($firstTenant, $user);
        TenantSet::dispatch($firstTenant, $user);
        TenantSet::dispatch($secondTenant, $user);

        $entries = AuditLog::query()
            ->where('action', AuditAction::TENANT_SWITCHED->value)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $entries);
        $this->assertSame($firstTenant->id, $entries[0]->tenant_id);
        $this->assertNull($entries[0]->payload['previous_tenant_id']);
        $this->assertSame($secondTenant->id, $entries[1]->tenant_id);
        $this->assertSame($firstTenant->id, $entries[1]->payload['previous_tenant_id']);
    }

    public function test_role_assignment_and_revocation_are_audited(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);
        $role = Role::query()->create([
            'name' => 'funnel-test-rolle',
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
            'is_tenant_role' => true,
        ]);

        $this->actingAs($user);
        $this->clearAuditLog();

        $tenantUser = $user->tenants()->where('tenant_id', $tenant->id)->first()->pivot;
        $tenantUser->assignRole($role);

        $assigned = AuditLog::query()->where('action', AuditAction::ROLE_ASSIGNED->value)->sole();

        $this->assertSame($tenant->id, $assigned->tenant_id);
        $this->assertSame($user->id, $assigned->user_id);
        $this->assertSame(['funnel-test-rolle'], $assigned->payload['roles']);
        $this->assertSame($user->id, $assigned->payload['affected_user_id']);

        $tenantUser->removeRole($role);

        $revoked = AuditLog::query()->where('action', AuditAction::ROLE_REVOKED->value)->sole();

        $this->assertSame(['funnel-test-rolle'], $revoked->payload['roles']);
        $this->assertSame($user->id, $revoked->payload['affected_user_id']);
    }

    public function test_every_action_has_a_german_label(): void
    {
        foreach (AuditAction::cases() as $action) {
            $label = $action->label();

            $this->assertNotSame(
                'funnel.audit.actions.'.$action->translationKey(),
                $label,
                sprintf('Fuer die Aktion "%s" fehlt eine Beschriftung in lang/de/funnel.php.', $action->value),
            );
        }
    }

    public function test_prepared_actions_for_later_tickets_exist(): void
    {
        $values = array_column(AuditAction::cases(), 'value');

        foreach ([
            'api_token.created',
            'api_token.deleted',
            'data.exported',
            'lead.purchased',
            'lead.state_forced',
        ] as $prepared) {
            $this->assertContains($prepared, $values);
        }
    }

    public function test_audit_config_keys_exist(): void
    {
        $this->assertIsString(config('funnel.audit.ip_salt'));
        $this->assertIsArray(config('funnel.audit.redacted_payload_keys'));
        $this->assertContains('password', config('funnel.audit.redacted_payload_keys'));
        $this->assertContains('ip', config('funnel.audit.redacted_payload_keys'));
    }
}
