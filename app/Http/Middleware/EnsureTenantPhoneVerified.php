<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ProblemResponse;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Die Telefonhuerde fuer die Management-API (LP-ONBOARD-027).
 *
 * EnsurePhoneVerified deckt jeden eingeloggten Screen ab, konnte unter
 * /api/v1 aber nicht greifen: Dort gehoert das Sanctum-Token dem **Tenant**
 * und nicht einem User (siehe ResolveTenantFromToken), waehrend
 * OnboardingService::isPhoneVerified einen User verlangt. Ohne diese
 * Middleware lieferten GET /api/v1/leads und /leads/{lead} Lead-Daten aus,
 * ohne dass irgendeine Rufnummer bestaetigt war -- und fuer gekaufte Leads
 * stehen dort die Kontaktdaten im Klartext.
 *
 * **Geprueft wird auf Workspace-Ebene.** Durchgelassen wird, wenn mindestens
 * ein Mitglied des Workspaces eine bestaetigte Rufnummer hat. Der Grund: Ein
 * Token haengt am Workspace, nicht an einer Person, und
 * `personal_access_tokens` fuehrt den erzeugenden User nicht mit. Die Huerde
 * ist damit etwas weicher als die Portal-Sperre -- sie beantwortet aber die
 * Frage, die fachlich dahinter steht: Kann dieser Workspace einen gekauften
 * Lead ueberhaupt anrufen? Ein Mitarbeiterwechsel sperrt die Integration
 * nicht aus, solange noch eine bestaetigte Nummer im Workspace steht.
 *
 * **Zuruecksetzen wirkt sofort.** Gelesen wird in jeder Anfrage direkt an
 * `users.phone_number_verified_at`; es gibt kein Flag am Tenant und keinen
 * Cache. Wer im Admin (LP-ONBOARD-017) oder ueber UserService::anonymize
 * zurueckgesetzt wird, faellt beim naechsten API-Request in die Sperre --
 * auch mit einem lange bestehenden Token.
 *
 * **403 statt Redirect.** Die API antwortet nach RFC 9457; ein Redirect auf
 * den Verifizierungs-Screen waere fuer einen Maschinen-Aufrufer wertlos.
 */
class EnsureTenantPhoneVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! EnsurePhoneVerified::isEnabled()) {
            return $next($request);
        }

        $tenant = $request->user();

        // Ohne Tenant-Token ist hier nichts zu entscheiden: Dann haben
        // auth:sanctum und ResolveTenantFromToken schon abgewiesen.
        if (! $tenant instanceof Tenant) {
            return $next($request);
        }

        if ($this->hasVerifiedMember($tenant)) {
            return $next($request);
        }

        return ProblemResponse::make(
            type: ProblemResponse::TYPE_PHONE_UNVERIFIED,
            title: __('api.problems.phone_unverified.title'),
            status: Response::HTTP_FORBIDDEN,
            detail: __('api.problems.phone_unverified.detail'),
        );
    }

    /**
     * Hat mindestens ein Mitglied des Workspaces eine bestaetigte Rufnummer?
     */
    private function hasVerifiedMember(Tenant $tenant): bool
    {
        return $tenant->users()
            ->whereNotNull('users.phone_number_verified_at')
            ->exists();
    }
}
