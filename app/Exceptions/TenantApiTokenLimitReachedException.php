<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Der Tenant hat die in config/funnel.php gesetzte Obergrenze gleichzeitig
 * gueltiger API-Tokens erreicht (FB-006).
 */
class TenantApiTokenLimitReachedException extends Exception
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct(__('funnel.api_token.limit_reached', ['limit' => $limit]));
    }
}
