<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Zustand einer Zustellung (FB-030e).
 */
enum WebhookDeliveryStatus: string
{
    /** Wartet auf den naechsten Versuch. */
    case PENDING = 'pending';

    /** Mit 2xx quittiert. */
    case DELIVERED = 'delivered';

    /** Der letzte Versuch ist fehlgeschlagen, weitere folgen. */
    case FAILED = 'failed';

    /** Alle Versuche sind verbraucht -- hier hoert die Zustellung auf. */
    case EXHAUSTED = 'exhausted';
}
