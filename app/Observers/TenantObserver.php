<?php

declare(strict_types=1);

namespace App\Observers;

use App\Constants\WalletOwnerType;
use App\Models\Tenant;
use App\Models\Wallet;

/**
 * Jeder neue Mandant bekommt sofort seinen Geldtopf (LP-WALLET-004).
 *
 * Damit ist Wallet::forBuyer()/forSeller() nur noch der Rueckfallweg fuer
 * Mandanten aus der Zeit vor dem Wallet -- im Regelbetrieb existiert das Wallet
 * schon, bevor die erste Buchung ansteht. Ein Wallet ohne Buchungen kostet
 * nichts und erspart der Kaufstrecke einen Sonderfall.
 *
 * Angelegt wird genau ein Topf, passend zur Rolle des Mandanten: Kaeufer kaufen
 * (BUYER), Betreiber verkaufen ihre Leads (SELLER). Wechselt ein Mandant
 * spaeter die Rolle oder tritt in beiden auf, legt der Rueckfallweg den zweiten
 * Topf bei Bedarf an.
 *
 * Die Ticketvorgabe nannte einen UserObserver; Wallets haengen im Funnel
 * Builder aber am Mandanten und nicht am Nutzer (wallets.owner_id zeigt auf
 * `tenants`), deshalb der TenantObserver.
 */
class TenantObserver
{
    /**
     * Der Vorgabepreis kommt aus config('wallet.default_lead_price_cents')
     * und nicht aus dem Spaltendefault der Migration (LP-WALLET-017): eine
     * Aenderung der Config soll sofort fuer alle neuen Mandanten gelten, ohne
     * dass dafuer eine Migration geschrieben werden muss.
     */
    public function creating(Tenant $tenant): void
    {
        if ($tenant->lead_price_cents === null) {
            $tenant->lead_price_cents = (int) config('wallet.default_lead_price_cents');
        }
    }

    public function created(Tenant $tenant): void
    {
        $ownerType = $tenant->isBuyer() ? WalletOwnerType::BUYER : WalletOwnerType::SELLER;

        Wallet::firstOrCreate(
            [
                'owner_type' => $ownerType->value,
                'owner_id' => $tenant->getKey(),
            ],
            ['currency' => config('wallet.currency')],
        );
    }
}
