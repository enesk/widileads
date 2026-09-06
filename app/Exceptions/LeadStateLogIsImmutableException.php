<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Eintraege des lead_state_log sind nach dem Anlegen unveraenderlich (FB-030).
 *
 * Das Protokoll ist der Beleg dafuer, wann ein Lead welchen Zustand hatte und
 * warum -- es traegt Gutschriften, Reklamationen und die Abrechnung. Waere es
 * nachtraeglich aenderbar, waere es als Beleg wertlos. Analog zum Audit-Log
 * (FB-005) brechen Aenderungs- und Loeschversuche deshalb hart ab.
 */
class LeadStateLogIsImmutableException extends RuntimeException
{
    public static function forUpdate(): self
    {
        return new self(__('funnel.lead.errors.log_not_updatable'));
    }

    public static function forDeletion(): self
    {
        return new self(__('funnel.lead.errors.log_not_deletable'));
    }
}
