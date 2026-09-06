<?php

declare(strict_types=1);

namespace App\Listeners\Audit;

use App\Constants\AuditAction;
use Spatie\Permission\Events\RoleDetached;

/**
 * FB-005: Haelt jeden Rollenentzug im Audit-Log fest.
 */
class LogRoleRevoked extends LogRoleChange
{
    public function handle(RoleDetached $event): void
    {
        $this->record(AuditAction::ROLE_REVOKED, $event->model, $event->rolesOrIds);
    }
}
