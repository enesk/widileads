<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Ereignisse einer oeffentlichen Sitzung sind append-only (FB-021).
 */
class SessionEventIsImmutableException extends RuntimeException
{
    public static function forUpdate(): self
    {
        return new self(__('runtime.errors.event_not_updatable'));
    }

    public static function forDeletion(): self
    {
        return new self(__('runtime.errors.event_not_deletable'));
    }
}
