<?php

declare(strict_types=1);

namespace App\Services\Twilio;

use App\Dto\CallerIdValidation;
use Throwable;
use Twilio\Rest\Client;

/**
 * Die echte Anbindung an Twilio (FB-080).
 *
 * Zugangsdaten kommen ausschliesslich aus config('services.twilio.*'). Fehlt
 * eines davon, wird gar nicht erst gewaehlt: Ein Aufruf ohne Zugangsdaten
 * scheitert sonst mit einer Twilio-Meldung, die niemand deuten kann.
 */
class TwilioCallerIdValidationClient implements CallerIdValidationClient
{
    public function requestValidation(
        string $phoneNumber,
        string $friendlyName,
        string $statusCallbackUrl,
    ): CallerIdValidation {
        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.token');

        if ($sid === '' || $token === '') {
            throw new CallerIdValidationFailed('Twilio ist nicht eingerichtet: services.twilio.sid oder .token fehlt.');
        }

        try {
            $request = (new Client($sid, $token))
                ->validationRequests
                ->create($phoneNumber, [
                    'friendlyName' => $friendlyName,
                    'statusCallback' => $statusCallbackUrl,
                    'statusCallbackMethod' => 'POST',
                ]);
        } catch (Throwable $exception) {
            throw new CallerIdValidationFailed($exception->getMessage(), 0, $exception);
        }

        // Twilio kennt fuer eine Validation Request keine eigene SID; die
        // Kennung des Bestaetigungsanrufs ist das, was sich im Twilio-Protokoll
        // wiederfinden laesst.
        return new CallerIdValidation(
            (string) $request->callSid,
            (string) $request->validationCode,
        );
    }
}
