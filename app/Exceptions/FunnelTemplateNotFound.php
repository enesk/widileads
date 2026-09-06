<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Zu einem Vorlagenschluessel gibt es keine Datei unter database/templates
 * (FB-019).
 */
class FunnelTemplateNotFound extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self(__('templates.errors.not_found', ['key' => $key]));
    }
}
