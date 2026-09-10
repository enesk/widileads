<?php

declare(strict_types=1);

namespace App\Http\Controllers\Twilio;

use App\Http\Controllers\Controller;
use App\Models\CallAttempt;
use App\Services\CallService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Standmeldungen zum Kaeufer-Bein eines Anrufversuchs (FB-082).
 *
 * Der Controller entscheidet nichts, er reicht die Rohmeldung an den Dienst
 * weiter. Erreichbar nur mit gueltiger Twilio-Signatur: Ohne sie koennte jeder
 * eine Gespraechsdauer melden -- und damit spaeter (FB-083) darueber
 * entscheiden, ob ein Lead als erreicht abgerechnet wird.
 */
class CallStatusController extends Controller
{
    public function __invoke(Request $request, CallAttempt $attempt, CallService $calls): Response
    {
        $calls->recordBuyerStatus($attempt, $request->post());

        return response()->noContent();
    }
}
