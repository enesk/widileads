<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Verkaufsart der Leads eines Funnels (FB-055).
 *
 * `exclusive` verkauft jeden Lead genau einmal -- der Kaeufer hat ihn allein.
 * `shared` verkauft denselben Lead an mehrere Kaeufer zu einem niedrigeren
 * Preis, bis die Hoechstzahl erreicht ist.
 *
 * Vorgabe ist `exclusive` (Entscheidung 2 vom 2026-09-06): Agenturen zahlen
 * fuer Exklusivitaet; Mehrfachverkauf senkt die Abschlussquote je Kaeufer und
 * treibt die Reklamationsquote.
 *
 * Der Wert steht so in der Datenbank und darf nach dem ersten Einsatz nicht
 * mehr geaendert werden.
 */
enum SaleMode: string
{
    /** Ein Lead, ein Kaeufer. */
    case EXCLUSIVE = 'exclusive';

    /** Ein Lead, mehrere Kaeufer -- bis `max_buyers` erreicht ist. */
    case SHARED = 'shared';

    public function isShared(): bool
    {
        return $this === self::SHARED;
    }

    public function label(): string
    {
        return __('marketplace.sale_mode.'.$this->value);
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
