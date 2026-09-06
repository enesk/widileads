<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Exceptions\AuditLogIsImmutableException;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query-Builder des Audit-Logs (FB-005). Er schliesst die Luecke, die
 * Model-Events offenlassen: Massen-Updates und -Loeschungen ueber den Builder
 * loesen keine Model-Events aus und werden deshalb hier abgefangen.
 *
 * @extends Builder<AuditLog>
 */
class AuditLogQueryBuilder extends Builder
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): int
    {
        throw AuditLogIsImmutableException::forUpdate();
    }

    public function delete(): mixed
    {
        throw AuditLogIsImmutableException::forDeletion();
    }

    public function forceDelete(): mixed
    {
        throw AuditLogIsImmutableException::forDeletion();
    }

    public function truncate(): void
    {
        throw AuditLogIsImmutableException::forDeletion();
    }
}
