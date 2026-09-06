<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Ein Funnel erfuellt die Voraussetzungen zur Veroeffentlichung nicht (FB-014).
 *
 * Traegt alle gefundenen Maengel, nicht nur den ersten -- wer einen Funnel
 * veroeffentlichen will, soll in einem Durchgang sehen, was fehlt.
 */
class FunnelNotPublishableException extends RuntimeException
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct(__('funnel.version.errors.not_publishable', [
            'reasons' => implode(' ', $reasons),
        ]));
    }
}
