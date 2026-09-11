<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Der Arbeitsstand eines Kaeufers zu einem gekauften Lead.
 *
 * Eine Merkhilfe fuer den Kaeufer, mehr nicht: Sie steht neben der Abrechnung
 * und nicht in ihr. "Kein Bedarf" hebt weder die Reservierung auf noch die
 * Frist -- darueber entscheiden allein Erreichbarkeit und LeadResolver. Der
 * Zustand des Leads selbst bleibt in `leads.lead_state` (Architekturleitsatz 1).
 *
 * Nicht zu verwechseln mit BuyerLeadFeedback: Das ist das abschliessende Urteil
 * ueber die Qualitaet eines Kaufs, hier steht der Stand der eigenen Bearbeitung.
 */
enum BuyerLeadStatus: string
{
    case OPEN = 'open';

    case APPOINTMENT = 'appointment';

    case OFFER_SENT = 'offer_sent';

    case NO_DEMAND = 'no_demand';

    public function label(): string
    {
        return __('marketplace.purchased.status.'.$this->value);
    }

    /**
     * @return array<string, string> Wert => Beschriftung, fuer Auswahlfelder.
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
