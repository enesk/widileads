<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Art des hinterlegten Zahlungsmittels eines Postpaid-Kaeufers
 * (LP-POSTPAID-002).
 *
 * Der Unterschied ist nicht kosmetisch: Eine SEPA-Lastschrift kann noch Wochen
 * nach der Gutschrift zurueckgegeben werden (Ruecklastschriftgebuehr,
 * SettlementStatus::RETURNED), eine Kartenzahlung scheitert dagegen sofort
 * oder gar nicht. Der Wert entspricht dem Typ des Stripe-Zahlungsmittels.
 *
 * Der Wert eines Case steht so in der Datenbank und darf nach dem ersten
 * Einsatz nicht mehr geaendert werden.
 */
enum PaymentMethodType: string
{
    /** SEPA-Lastschriftmandat. */
    case SEPA_DEBIT = 'sepa_debit';

    /** Kredit- oder Debitkarte. */
    case CARD = 'card';

    /**
     * Kann eine bereits gutgeschriebene Zahlung dieses Mittels noch
     * zurueckgegeben werden?
     */
    public function canBeReturned(): bool
    {
        return $this === self::SEPA_DEBIT;
    }

    /**
     * Braucht dieses Mittel ein erteiltes SEPA-Lastschriftmandat, bevor
     * eingezogen werden darf?
     */
    public function requiresMandate(): bool
    {
        return $this === self::SEPA_DEBIT;
    }

    /**
     * Muss vor jedem Einzug ueber dieses Mittel eine Vorabankuendigung an den
     * Kaeufer gehen (LP-POSTPAID-014)?
     *
     * Nur bei der SEPA-Basislastschrift: Dort ist der Zahlungsempfaenger
     * verpflichtet, Betrag und Belastungsdatum vorher anzukuendigen. Eine
     * Kartenzahlung kennt diese Pflicht nicht, sie wird sofort entschieden.
     */
    public function requiresPrenotification(): bool
    {
        return $this === self::SEPA_DEBIT;
    }

    /**
     * Bezeichnung fuer die Oberflaeche.
     */
    public function label(): string
    {
        return __('marketplace.wallet.payment_methods.types.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
