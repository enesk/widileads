<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Exceptions\CreditLedgerIsImmutableException;
use App\Models\CreditLedgerEntry;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query-Builder des Guthabenkontos (FB-052). Er schliesst die Luecke, die
 * Model-Events offenlassen: Massen-Updates und -Loeschungen ueber den Builder
 * loesen keine Model-Events aus und werden deshalb hier abgefangen.
 *
 * Gleiches Muster wie AuditLogQueryBuilder (FB-005) und
 * LeadStateLogQueryBuilder (FB-030).
 *
 * @extends Builder<CreditLedgerEntry>
 */
class CreditLedgerQueryBuilder extends Builder
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): int
    {
        throw CreditLedgerIsImmutableException::forUpdate();
    }

    public function delete(): mixed
    {
        throw CreditLedgerIsImmutableException::forDeletion();
    }

    public function forceDelete(): mixed
    {
        throw CreditLedgerIsImmutableException::forDeletion();
    }

    public function truncate(): void
    {
        throw CreditLedgerIsImmutableException::forDeletion();
    }
}
