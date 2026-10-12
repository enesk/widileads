<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CallerId;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CallerIdService;

/**
 * Die Frage „fehlt diesem Kaeufer eine nutzbare Rufnummernanzeige?".
 *
 * **Keine Sperre mehr** (Ticket #49): Die Middleware RequireVerifiedCallerId,
 * die einen Kaeufer ohne nutzbare Nummer auf `portal.caller-id` festhielt, ist
 * entfallen. Sie unterschied sich von der Telefonsperre (EnsurePhoneVerified)
 * in genau einem Fall -- einer `caller_ids`-Zeile in `failed` oder `expired` --
 * und verlangte dort einen zweiten Nachweis fuer eine Nummer, die Twilio Verify
 * kurz vorher bestaetigt hatte; auf einem Trial-Konto eine Sackgasse. Die
 * Rufnummernanzeige ist Voraussetzung fuer den Anruf und nicht fuer das Portal.
 *
 * Geblieben ist die Frage selbst: Sie steuert den Hinweis auf der
 * Rufnummernkarte des Dashboards und im Lead-Detail -- dort, wo die Nummer
 * gebraucht wird. Verweigert wird der Anruf im CallService
 * (`call.attempt.errors.caller_id_missing`), nicht hier.
 *
 * **Die Frage gilt fuer jeden Workspace des Portals.** Sie war zuerst auf
 * Mandanten vom Typ `buyer` beschraenkt -- und lief damit ins Leere: Wer sich
 * ueber `/register` anmeldet, bekommt einen Workspace vom Typ `operator`
 * (TenantCreationService::createTenant hat OPERATOR als Vorgabe), auch wenn er
 * als Kaeufer gekommen ist. Ein Kaeufer-Mandant entsteht allein ueber die
 * Kaeuferregistrierung unter /kaeufer/registrierung. Der Typ taugt deshalb
 * nicht als Unterscheidung; sie fragt ihn nicht mehr.
 *
 * **Die Bestaetigung haengt am Benutzer, nicht am Workspace** (siehe
 * CallerIdService::forUser). Wer sie in einem Workspace erledigt hat, hat sie
 * in allen.
 */
final class PortalCallerIdLock
{
    /**
     * Die Seite, auf der ein Kaeufer seine Rufnummernanzeige regelt. Sie bleibt
     * auch waehrend des Einrichtungs-Wizards erreichbar
     * (EnsureOnboardingWizard::ALLOWED_ROUTE_NAMES).
     */
    public const ALLOWED_ROUTE = 'portal.caller-id';

    /**
     * Fehlt diesem Benutzer in diesem Workspace eine nutzbare Nummer?
     *
     * Gelesen wird das nur noch von der Rufnummernseite selbst. Niemand wird
     * dadurch umgeleitet -- siehe Klassenkommentar.
     */
    public static function locks(?User $user, ?Tenant $tenant): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        if (! $user instanceof User || ! $tenant instanceof Tenant) {
            return false;
        }

        return ! self::hasVerifiedNumber($user);
    }

    /**
     * Ist der Hinweis ueberhaupt eingeschaltet?
     *
     * `funnel.call.caller_id.required_for_portal` war die Notbremse fuer die
     * entfallene Sperre -- Bestaetigungsanrufe sind auf einem
     * Twilio-Trial-Konto gesperrt (HTTP 400, "Placing verification calls is not
     * supported on trial accounts"), und dort kam kein Kaeufer an ihr vorbei.
     * **Seit Ticket #49 ist der Schalter nur noch eine Anzeigeoption:** Er
     * blendet den Hinweis aus, sperrt aber nichts. Wer hier eine Sperre zum
     * Abschalten sucht, sucht umsonst -- den Anruf verweigert der CallService.
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
