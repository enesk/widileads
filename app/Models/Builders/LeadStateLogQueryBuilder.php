<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Exceptions\LeadStateLogIsImmutableException;
use App\Models\LeadStateLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query-Builder des Zustandsprotokolls (FB-030). Er schliesst die Luecke, die
 * Model-Events offenlassen: Massen-Updates und -Loeschungen ueber den Builder
 * loesen keine Model-Events aus und werden deshalb hier abgefangen.
 *
 * Gleiches Muster wie AuditLogQueryBuilder (FB-005).
 *
 * @extends Builder<LeadStateLog>
 */
class LeadStateLogQueryBuilder extends Builder
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): int
    {
        throw LeadStateLogIsImmutableException::forUpdate();
    }

    public function delete(): mixed
    {
        throw LeadStateLogIsImmutableException::forDeletion();
    }

    public function forceDelete(): mixed
    {
        throw LeadStateLogIsImmutableException::forDeletion();
    }

    public function truncate(): void
    {
        throw LeadStateLogIsImmutableException::forDeletion();
    }
}
