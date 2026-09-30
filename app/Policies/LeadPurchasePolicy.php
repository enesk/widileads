<?php

declare(strict_types=1);

namespace App\Policies;

use App\Constants\TenancyPermissionConstants;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;

/**
 * Wer welchen Leadkauf sehen und benutzen darf (Ticket #2).
 *
 * Zwei Ebenen, und beide muessen stimmen: Zuerst der Mandant -- ein Kauf
 * gehoert dem Workspace, der ihn bezahlt hat --, dann die Person. Seit Ticket
 * #1 traegt der Beleg mit `purchased_by_user_id`, wer geklickt hat.
 *
 * Drei Faelle, und der dritte ist der, an dem die Trennung haengt:
 *
 *  1. **Eigener Kauf.** Alles: sehen, anrufen, Kontakt, reklamieren.
 *  2. **Kauf ohne Nutzer.** Der Autokauf (FB-056) entscheidet ueber ein
 *     Kaufprofil, nicht ueber einen Menschen -- so ein Kauf gehoert dem
 *     Mandanten als Ganzem. Jedes Mitglied darf ihn behandeln wie einen
 *     eigenen. Ohne diese Regel koennte niemand den Lead anrufen, die Frist
 *     liefe aber weiter und der Kauf wuerde am Ende abgerechnet.
 *  3. **Kauf eines Kollegen.** Nur lesen, und nur mit dem Recht
 *     `view team leads` ("Team Leads"). Anrufen und Kontaktdaten bleiben beim
 *     Kaeufer -- auch fuer einen Admin.
 *
 * Die Kontaktdaten selbst maskiert diese Klasse nicht: Darueber entscheidet
 * ausschliesslich der LeadContactResolver. Hier steht nur, wer den Kauf
 * ueberhaupt in die Hand nehmen darf.
 */
class LeadPurchasePolicy
{
    public function __construct(private readonly TenantPermissionService $tenantPermissions) {}

    /**
     * Den Kauf ansehen -- eigener, herrenloser oder, mit dem Recht, der eines
     * Kollegen.
     */
    public function view(User $user, LeadPurchase $purchase): bool
    {
        if (! $this->belongsToBuyer($user, $purchase)) {
            return false;
        }

        if ($this->isOwnOrUnassigned($user, $purchase)) {
            return true;
        }

        return $this->viewTeam($user, $purchase->buyer);
    }

    /**
     * Den Kauf benutzen: anrufen, Kontakt aufdecken, reklamieren, Notiz und
     * Stand setzen.
     *
     * Bewusst enger als `view`: Der Kauf eines Kollegen bleibt lesbar, aber
     * unantastbar. Wer den Kontakt braucht, geht ueber den Kollegen, der
     * gekauft hat.
     */
    public function act(User $user, LeadPurchase $purchase): bool
    {
        return $this->belongsToBuyer($user, $purchase)
            && $this->isOwnOrUnassigned($user, $purchase);
    }

    /**
     * Darf dieser Nutzer die Kaeufe des ganzen Mandanten sehen?
     *
     * Die Frage der Seite "Team Leads" (Ticket #3) -- sie haengt am Mandanten
     * und nicht an einem einzelnen Beleg. Gleichbedeutend mit dem Gate
     * `lead-purchases.view-team`.
     */
    public function viewTeam(User $user, ?Tenant $buyer): bool
    {
        if (! $buyer instanceof Tenant || ! $user->canAccessTenant($buyer)) {
            return false;
        }

        return $this->tenantPermissions->tenantUserHasPermissionTo(
            $buyer,
            $user,
            TenancyPermissionConstants::PERMISSION_VIEW_TEAM_LEADS,
        );
    }

    /**
     * Gehoert der Kauf zu einem Workspace dieses Nutzers?
     *
     * Die Mandantentrennung steht vor allem anderen: Ohne sie waere jede
     * weitere Frage die falsche.
     */
    private function belongsToBuyer(User $user, LeadPurchase $purchase): bool
    {
        $buyer = $purchase->buyer;

        return $buyer instanceof Tenant && $user->canAccessTenant($buyer);
    }

    /**
     * Eigener Kauf -- oder einer, den niemand ausgeloest hat.
     */
    private function isOwnOrUnassigned(User $user, LeadPurchase $purchase): bool
    {
        return $purchase->purchased_by_user_id === null
            || (int) $purchase->purchased_by_user_id === (int) $user->getKey();
    }
}
