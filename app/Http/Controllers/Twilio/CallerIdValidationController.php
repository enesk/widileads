<?php

declare(strict_types=1);

namespace App\Http\Controllers\Twilio;

use App\Http\Controllers\Controller;
use App\Services\CallerIdService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Ergebnis des Bestaetigungsanrufs von Twilio (FB-080).
 *
 * Erreichbar nur mit gueltiger Twilio-Signatur (VerifyTwilioSignature). Der
 * Controller entscheidet nichts, er reicht das Ergebnis an den Dienst weiter --
 * der Stand einer Rufnummer entsteht an genau einer Stelle.
 */
class CallerIdValidationController extends Controller
{
    public function __invoke(Request $request, CallerIdService $callerIds): Response
    {
        $phoneNumber = (string) $request->string('To');

        if ($phoneNumber !== '') {
            $callerIds->completeValidation(
                $phoneNumber,
                $request->string('VerificationStatus')->toString() === 'success',
            );
        }

        // Twilio erwartet keine Antwort mit Inhalt; alles andere wuerde nur als
        // fehlerhaftes TwiML im Twilio-Protokoll landen.
        return response()->noContent();
    }
}
