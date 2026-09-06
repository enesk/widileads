<?php

declare(strict_types=1);

namespace App\Listeners\Audit;

use App\Constants\AuditAction;
use Spatie\Permission\Events\RoleAttached;

/**
 * FB-005: Haelt jede Rollenzuweisung im Audit-Log fest.
 */
class LogRoleAssigned extends LogRoleChange
{
    public function handle(RoleAttached $event): void
    {
        $this->record(AuditAction::ROLE_ASSIGNED, $event->model, $event->rolesOrIds);
    }
}
