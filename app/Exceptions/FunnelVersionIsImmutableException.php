<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Eine veroeffentlichte Funnel-Version ist unveraenderlich (FB-014).
 */
class FunnelVersionIsImmutableException extends RuntimeException
{
    public static function forUpdate(): self
    {
        return new self(__('funnel.version.errors.not_updatable'));
    }

    public static function forDeletion(): self
    {
        return new self(__('funnel.version.errors.not_deletable'));
    }
}
