<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Audit-Eintraege sind nach dem Anlegen unveraenderlich (FB-005). Jeder Versuch,
 * einen Eintrag zu aendern oder zu loeschen, bricht mit dieser Ausnahme ab.
 */
class AuditLogIsImmutableException extends RuntimeException
{
    public static function forUpdate(): self
    {
        return new self(__('funnel.audit.errors.not_updatable'));
    }

    public static function forDeletion(): self
    {
        return new self(__('funnel.audit.errors.not_deletable'));
    }
}
