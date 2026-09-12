<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Zahlungsmodus eines Kaeufer-Mandanten (LP-POSTPAID-002).
 *
 * Der Modus entscheidet, wann das Geld fliesst: `prepaid` kauft aus dem
 * vorhandenen Guthaben, `postpaid` kauft gegen einen Kreditrahmen und zahlt im
 * Nachhinein per Lastschrift oder Karte. Alles Weitere haengt daran -- die
 * Deckungspruefung im WalletService, der Aufschlag am Marktplatz und der
 * Einzug durch den Scheduler.
 *
 * Der Wert eines Case steht so in der Datenbank und darf nach dem ersten
 * Einsatz nicht mehr geaendert werden.
 */
enum PaymentMode: string
{
    /** Regelfall: gekauft wird nur aus aufgeladenem Guthaben. */
    case PREPAID = 'prepaid';

    /** Freigeschaltet: gekauft wird gegen Kreditrahmen, abgerechnet wird spaeter. */
    case POSTPAID = 'postpaid';

    /**
     * Darf dieser Modus den Saldo bis zum Kreditrahmen ins Minus fuehren?
     */
    public function allowsCredit(): bool
    {
        return $this === self::POSTPAID;
    }

    /**
     * Faellt auf Kaeufe in diesem Modus der Postpaid-Aufschlag an?
     */
    public function hasSurcharge(): bool
    {
        return $this === self::POSTPAID;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
