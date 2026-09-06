<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Stand der Freischaltung eines Kaeufer-Mandanten (FB-050).
 *
 * Ein Kaeufer entsteht durch Selbstregistrierung und ist danach zunaechst
 * `pending`: der Mandant existiert, kommt aber an keinen Lead. Erst der
 * Plattform-Admin entscheidet -- Freischaltung (`active`) oder Ablehnung
 * (`rejected`). Es gibt bewusst keinen automatischen Weg nach `active`.
 *
 * Der Wert steht so in der Datenbank und darf nach dem ersten Einsatz nicht
 * mehr geaendert werden.
 */
enum BuyerRegistrationStatus: string
{
    /** Registrierung eingegangen, noch nicht geprueft. Kein Marktplatzzugriff. */
    case PENDING = 'pending';

    /** Vom Plattform-Admin freigeschaltet. Einziger Zustand mit Marktplatzzugriff. */
    case ACTIVE = 'active';

    /** Vom Plattform-Admin abgelehnt. Kein Marktplatzzugriff. */
    case REJECTED = 'rejected';

    /**
     * Der einzige Zustand, in dem ein Kaeufer den Marktplatz erreicht.
     *
     * Bewusst als Positivliste formuliert: kommt spaeter ein Zustand dazu
     * (etwa "suspended"), sperrt er von sich aus, statt versehentlich
     * durchzulassen.
     */
    public function grantsMarketplaceAccess(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Steht die Entscheidung des Plattform-Admins noch aus?
     */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function label(): string
    {
        return __('funnel.buyer.status.'.$this->value);
    }

    /**
     * @return array<string, string> Wert => Label, fuer Auswahl- und Filterfelder.
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
