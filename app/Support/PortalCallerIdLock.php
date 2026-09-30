<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CallerId;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CallerIdService;

/**
 * Die Sperre des Kaeuferportals bis zur bestaetigten Rufnummer.
 *
 * Ein frisch registrierter Kaeufer soll nichts tun koennen, bevor seine eigene
 * Rufnummer bestaetigt ist: Ohne sie kann er einen gekauften Lead nicht
 * anrufen, und der Anruf ist der einzige Weg, auf dem ein Lead abgerechnet
 * oder freigegeben wird. Ein Kauf davor waere Geld ohne Gegenwert.
 *
 * Die Entscheidung steht hier und nicht in der Middleware, weil zwei Stellen
 * sie brauchen: RequireVerifiedCallerId leitet um, und der Rahmen des
 * Arbeitsbereichs blendet Navigation und Kontomenue aus. Liefen beide
 * auseinander, stuende eine Navigation voller Verweise da, die alle auf
 * dieselbe Seite zurueckspringen.
 *
 * **Die Sperre gilt nur fuer Kaeufer-Mandanten.** Ein Betreiber-Workspace
 * verkauft Leads und ruft niemanden an; fuer ihn gaebe es nichts zu
 * bestaetigen, und die Sperre waere eine Tuer ohne Schluessel.
 *
 * **Die Bestaetigung haengt am Benutzer, nicht am Workspace** (siehe
 * CallerIdService::forUser). Wer sie in einem Workspace erledigt hat, ist in
 * allen frei.
 */
final class PortalCallerIdLock
{
    /**
     * Die einzige Seite, die ein gesperrter Kaeufer erreicht.
     */
    public const ALLOWED_ROUTE = 'portal.caller-id';

    /**
     * Haelt die Sperre diesen Benutzer in diesem Workspace fest?
     */
    public static function locks(?User $user, ?Tenant $tenant): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        if (! $user instanceof User || ! $tenant instanceof Tenant) {
            return false;
        }

        if (! $tenant->isBuyer()) {
            return false;
        }

        return ! self::hasVerifiedNumber($user);
    }

    /**
     * Ist die Sperre ueberhaupt eingeschaltet?
     *
     * Der Schalter ist kein Schmuck: Bestaetigungsanrufe sind auf einem
     * Twilio-Trial-Konto gesperrt (HTTP 400, "Placing verification calls is not
     * supported on trial accounts"). Auf einem solchen Konto kaeme kein Kaeufer
     * je an der Sperre vorbei -- dort gehoert sie aus, bis das Konto
     * aufgewertet ist.
     */
    public static function isEnabled(): bool
    {
        return (bool) config('funnel.call.caller_id.required_for_portal', true);
    }

    /**
     * Hat der Benutzer eine bestaetigte, nicht abgelaufene Nummer?
     */
    public static function hasVerifiedNumber(User $user): bool
    {
        $callerId = app(CallerIdService::class)->forUser($user);

        return $callerId instanceof CallerId && $callerId->isUsableAsCallerId();
    }
}
