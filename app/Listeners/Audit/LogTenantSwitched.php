<?php

declare(strict_types=1);

namespace App\Listeners\Audit;

use App\Constants\AuditAction;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Filament\Events\TenantSet;
use Illuminate\Http\Request;

/**
 * FB-005: Haelt den Wechsel des aktiven Mandanten im Audit-Log fest.
 *
 * Filament setzt den Mandanten bei jedem Request des Dashboards, nicht nur beim
 * Wechsel. Damit nicht jeder Seitenaufruf einen Eintrag erzeugt, merkt sich der
 * Listener den zuletzt protokollierten Mandanten in der Session und schreibt
 * nur, wenn sich dieser tatsaechlich aendert. Der erste Mandant einer Session
 * gilt dabei als Wechsel (previous_tenant_id ist dann null).
 */
class LogTenantSwitched
{
    /**
     * Session-Schluessel des zuletzt protokollierten Mandanten.
     */
    public const SESSION_KEY = 'auditLastTenantId';

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly Request $request,
    ) {}

    public function handle(TenantSet $event): void
    {
        $tenant = $event->getTenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $previousTenantId = null;

        if ($this->request->hasSession()) {
            $session = $this->request->session();
            $previousTenantId = $session->get(self::SESSION_KEY);

            if ($previousTenantId !== null && (int) $previousTenantId === (int) $tenant->getKey()) {
                return;
            }

            $session->put(self::SESSION_KEY, $tenant->getKey());
        }

        $this->auditLogger->log(
            AuditAction::TENANT_SWITCHED,
            subject: $tenant,
            payload: [
                'previous_tenant_id' => $previousTenantId === null ? null : (int) $previousTenantId,
                'tenant_id' => (int) $tenant->getKey(),
            ],
            tenant: $tenant,
        );
    }
}
