<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Wem ein Wallet gehoert (LP-WALLET-002).
 *
 * Ein Mandant kann beide Rollen haben -- er kauft Leads und verkauft eigene.
 * Deshalb entscheidet nicht der Mandantentyp ueber das Wallet, sondern diese
 * Rolle: Kauf- und Verkaufsguthaben sind getrennte Toepfe mit getrennten
 * Ledgern.
 *
 * `PLATFORM` hat keinen Besitzer in der Datenbank; das Wallet wird ueber
 * config('wallet.platform_wallet_owner') gefunden.
 *
 * Der Wert eines Case steht so in der Datenbank und darf nach dem ersten
 * Einsatz nicht mehr geaendert werden.
 */
enum WalletOwnerType: string
{
    /** Guthaben, aus dem Leads gekauft werden. */
    case BUYER = 'buyer';

    /** Einnahmen aus verkauften Leads, Grundlage der Auszahlung. */
    case SELLER = 'seller';

    /** Wallet der Plattform: Provisionen, Erstattungen, Korrekturen. */
    case PLATFORM = 'platform';

    /**
     * Haengt dieses Wallet an einem Mandanten? Fuer die Plattform nicht.
     */
    public function belongsToTenant(): bool
    {
        return $this !== self::PLATFORM;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
