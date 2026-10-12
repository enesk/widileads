<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Onboarding;

use App\Events\Onboarding\TourSeen;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * "Diese Tour ist durch" (LP-ONBOARD-013).
 *
 * Das Skript meldet hierher, sobald eine Tour abgeschlossen oder uebersprungen
 * wurde. Der Controller entscheidet nichts und rechnet nicht: Er loest das
 * Ereignis aus, der Zuhoerer hakt den Schritt ab (LP-ONBOARD-011). Welcher
 * Schritt das ist, steht in App\Support\PortalTour.
 *
 * Antwort ist 204 und kein Rumpf. Der Aufruf kommt per fetch aus dem Browser,
 * waehrend der Kaeufer auf der Seite bleibt -- es gibt nichts anzuzeigen und
 * nichts umzuleiten. Ein unbekannter Tourname kommt hier nie an, die Route
 * schraenkt den Parameter auf die bekannten Namen ein.
 *
 * Mehrfach aufrufbar: Wer die Tour aus dem Kontomenue erneut startet und
 * beendet, meldet erneut. Das Abhaken ist idempotent.
 */
class TourSeenController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant, string $name): Response
    {
        /*
         | Nur Kaeufer-Workspaces fuehren Onboarding-Schritte. Ein
         | Betreiber-Mandant hat weder Guthaben noch gekaufte Leads -- ein Haken
         | in seiner Fortschrittstabelle wuerde den Admin-Trichter verfaelschen.
         */
        if ($tenant->isBuyer()) {
            event(new TourSeen($tenant, $name, $request->user()));
        }

        return response()->noContent();
    }
}
