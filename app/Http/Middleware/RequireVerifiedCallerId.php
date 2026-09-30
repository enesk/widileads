<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\PortalCallerIdLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Haelt einen Kaeufer ohne bestaetigte Rufnummer auf der Bestaetigungsseite
 * fest.
 *
 * Erste Handlung im Portal ist die eigene Rufnummer, alles andere danach:
 * Ohne bestaetigte Nummer kann ein Kaeufer einen gekauften Lead nicht anrufen,
 * und ohne Anruf wird der Lead weder abgerechnet noch freigegeben. Wer vorher
 * kauft, legt Geld auf einen Vorgang, den er nicht abschliessen kann.
 *
 * Umgeleitet wird, nicht abgebrochen: Ein 403 waere hier eine Sackgasse, die
 * Weiterleitung ist der naechste Schritt. Die Bestaetigungsseite selbst bleibt
 * frei, sonst leitete sie auf sich selbst.
 *
 * Wen sie NICHT betrifft, steht in App\Support\PortalCallerIdLock -- dieselbe
 * Entscheidung liest der Rahmen des Arbeitsbereichs, der waehrend der Sperre
 * die Navigation ausblendet.
 *
 * Reihenfolge: Sie haengt hinter `portal.tenant`. Vorher steht der Mandant
 * nicht, und ohne ihn ist nicht zu entscheiden, ob es ein Kaeufer-Workspace
 * ist.
 */
class RequireVerifiedCallerId
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');

        if (! $tenant instanceof Tenant) {
            return $next($request);
        }

        if ($request->route()?->getName() === PortalCallerIdLock::ALLOWED_ROUTE) {
            return $next($request);
        }

        if (! PortalCallerIdLock::locks($request->user(), $tenant)) {
            return $next($request);
        }

        return redirect()->route(PortalCallerIdLock::ALLOWED_ROUTE, ['tenant' => $tenant->uuid]);
    }
}
