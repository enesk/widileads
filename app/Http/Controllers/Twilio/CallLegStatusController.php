<?php

declare(strict_types=1);

namespace App\Http\Controllers\Twilio;

use App\Http\Controllers\Controller;
use App\Models\CallAttempt;
use App\Services\CallService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Zwischenstaende des Lead-Beins (FB-081/FB-082).
 *
 * Die Bridge meldet initiated, ringing, answered und completed getrennt vom
 * Kaeufer-Bein. Daraus stammen der Zeitpunkt des Abnehmens und die Kennung des
 * durchgestellten Anrufs -- das Ergebnis selbst kommt erst mit dem
 * Dial-Rueckruf. Erreichbar nur mit gueltiger Twilio-Signatur.
 */
class CallLegStatusController extends Controller
{
    public function __invoke(Request $request, CallAttempt $attempt, CallService $calls): Response
    {
        $calls->recordLegStatus($attempt, $request->post());

        return response()->noContent();
    }
}
