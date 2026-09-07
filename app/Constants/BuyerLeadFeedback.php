<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Rueckmeldung eines Kaeufers zu einem gekauften Lead (FB-057).
 *
 * Bewusst **kein** Lead-Zustand: `leads.lead_state` bleibt die einzige
 * Zustandsspalte eines Leads (Architekturleitsatz 1). Ob ein Kaeufer einen Lead
 * brauchbar fand, ist seine Meinung ueber einen Kauf -- sie haengt am
 * Kaufbeleg, nicht am Lead. Zwei Kaeufer desselben geteilten Leads koennen
 * unterschiedlich urteilen, und beides ist richtig.
 *
 * Eine Reklamation ist etwas anderes und kommt mit FB-058: Dort wird ein
 * Zustand beantragt, hier nur eine Einschaetzung festgehalten.
 */
enum BuyerLeadFeedback: string
{
    /** Der Lead war brauchbar. */
    case INTERESTED = 'interested';

    /** Der Lead war nichts fuer diesen Kaeufer. */
    case NOT_INTERESTED = 'not_interested';

    public function label(): string
    {
        return __('marketplace.purchased.feedback.'.$this->value);
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
