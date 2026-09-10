<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Erreichbarkeit eines ausgelieferten Leads (FB-082).
 *
 * Zweite Achse neben `lead_state` (Architekturleitsatz 1): `lead_state` sagt,
 * wo der Lead im Verkauf steht, `contact_status` sagt, ob der Kaeufer ihn ans
 * Telefon bekommen hat. Beide bewegen sich unabhaengig voneinander.
 *
 * Gesetzt wird der Wert ausschliesslich vom LeadResolver (FB-083) -- weder das
 * Portal noch ein Webhook schreibt hier direkt hinein.
 */
enum LeadContactStatus: string
{
    /** Frist laeuft, es darf weiter angerufen werden. */
    case OPEN = 'open';

    /** Endstand: erreicht oder Frist abgelaufen -- der Lead wird abgerechnet. */
    case BILLABLE = 'billable';

    /** Endstand: Versuche ausgeschoepft -- Gutschrift. */
    case UNREACHABLE = 'unreachable';

    /**
     * Ist die Erreichbarkeit entschieden? Danach zaehlt kein Versuch mehr.
     */
    public function isResolved(): bool
    {
        return $this !== self::OPEN;
    }

    public function label(): string
    {
        return __('call.contact_status.'.$this->value);
    }
}
