<?php

declare(strict_types=1);

namespace App\Listeners\Audit;

use App\Constants\AuditAction;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role as RoleContract;

/**
 * FB-005: Gemeinsame Auswertung der Spatie-Rollenereignisse.
 *
 * Die Ereignisse liefern das betroffene Model (bei Mandantenrollen der Pivot
 * TenantUser, sonst der User) und die Rollen als Objekte oder als IDs. Der
 * Listener loest daraus Mandant, betroffenen Benutzer und Rollennamen auf.
 * Der handelnde Benutzer kommt aus dem AuditLogger (angemeldeter Benutzer).
 *
 * Die Ereignisse werden nur ausgeloest, wenn config('permission.events_enabled')
 * gesetzt ist -- deshalb steht der Schalter in config/permission.php auf true.
 */
abstract class LogRoleChange
{
    public function __construct(protected readonly AuditLogger $auditLogger) {}

    protected function record(AuditAction $action, Model $model, mixed $rolesOrIds): void
    {
        $roleNames = $this->resolveRoleNames($rolesOrIds);

        if ($roleNames === []) {
            return;
        }

        $this->auditLogger->log(
            $action,
            subject: $model,
            payload: [
                'roles' => $roleNames,
                'affected_user_id' => $this->resolveAffectedUserId($model),
                'model_type' => $model->getMorphClass(),
                'model_id' => $model->getKey(),
            ],
            tenant: $this->resolveTenant($model),
        );
    }

    /**
     * @return list<string>
     */
    private function resolveRoleNames(mixed $rolesOrIds): array
    {
        $roles = match (true) {
            $rolesOrIds instanceof Collection => $rolesOrIds->all(),
            is_array($rolesOrIds) => $rolesOrIds,
            default => [$rolesOrIds],
        };

        $names = [];
        $ids = [];

        foreach ($roles as $role) {
            if ($role instanceof RoleContract) {
                $names[] = (string) $role->name;

                continue;
            }

            if (is_int($role) || is_string($role)) {
                $ids[] = $role;
            }
        }

        if ($ids !== []) {
            /** @var list<string> $resolved */
            $resolved = Role::query()
                ->withoutGlobalScopes()
                ->whereKey($ids)
                ->pluck('name')
                ->all();

            $names = array_merge($names, $resolved);
        }

        return array_values(array_unique($names));
    }

    private function resolveAffectedUserId(Model $model): ?int
    {
        if ($model instanceof TenantUser) {
            return $model->user_id === null ? null : (int) $model->user_id;
        }

        if ($model instanceof User) {
            return (int) $model->getKey();
        }

        return null;
    }

    private function resolveTenant(Model $model): ?Tenant
    {
        if (! $model instanceof TenantUser || $model->tenant_id === null) {
            return null;
        }

        return Tenant::query()->find($model->tenant_id);
    }
}
