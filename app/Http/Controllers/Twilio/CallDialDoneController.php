<?php

declare(strict_types=1);

namespace App\Http\Controllers\Twilio;

use App\Http\Controllers\Controller;
use App\Models\CallAttempt;
use App\Services\CallService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Ergebnis des Dial-Verbs (FB-082) -- die abschliessende Meldung zu einem
 * Anrufversuch.
 *
 * Sie traegt Stand und Dauer des Anrufs beim Lead und entscheidet damit ueber
 * die Abrechnung. Erreichbar nur mit gueltiger Twilio-Signatur: Ohne sie
 * koennte jeder eine Gespraechsdauer melden und einen Lead als erreicht
 * abrechnen lassen.
 *
 * Die Antwort ist eine Anweisung, kein leerer Rumpf: Twilio wartet nach dem
 * Dial auf das naechste Verb und laesst den Kaeufer sonst in der Leitung.
 */
class CallDialDoneController extends Controller
{
    public function __invoke(Request $request, CallAttempt $attempt, CallService $calls): Response
    {
        $calls->recordDialResult($attempt, $request->post());

        return response(
            (string) $calls->hangUpInstruction(),
            Response::HTTP_OK,
        )->header('Content-Type', 'text/xml');
    }
}
