<?php

declare(strict_types=1);

namespace App\Services\Twilio;

use Throwable;
use Twilio\Rest\Client;

/**
 * Die echte Anbindung an Twilio fuer ausgehende Anrufe (FB-081).
 *
 * Zugangsdaten ausschliesslich aus config('twilio.*') -- der alte Ort aus
 * SaaSykit bleibt Rueckfallebene, beide lesen dieselben Env-Schluessel. Fehlt
 * eines davon, wird gar nicht erst gewaehlt.
 */
class TwilioOutboundCallClient implements OutboundCallClient
{
    public function call(string $to, string $from, string $answerUrl, string $statusCallbackUrl): string
    {
        $client = $this->client();

        try {
            $call = $client->calls->create($to, $from, [
                'url' => $answerUrl,
                'method' => 'POST',
                'statusCallback' => $statusCallbackUrl,
                'statusCallbackMethod' => 'POST',
                // Nur die beiden Meldungen, aus denen sich ein Ergebnis
                // ableiten laesst -- Zwischenstaende kosten Rueckrufe, ohne
                // etwas zu entscheiden.
                'statusCallbackEvent' => ['answered', 'completed'],

                // Klingeldauer beim Kaeufer. Nimmt er nicht ab, gibt Twilio
                // auf, bevor beim Lead ueberhaupt gewaehlt wird.
                'timeout' => (int) config('lead_calls.buyer_ring_timeout'),
            ]);
        } catch (Throwable $exception) {
            throw new OutboundCallFailed($exception->getMessage(), 0, $exception);
        }

        return (string) $call->sid;
    }

    public function hangUp(string $sid): void
    {
        try {
            $this->client()->calls->getContext($sid)->update(['status' => 'completed']);
        } catch (Throwable $exception) {
            throw new OutboundCallFailed($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @throws OutboundCallFailed
     */
    private function client(): Client
    {
        $sid = (string) (config('twilio.account_sid') ?: config('services.twilio.sid'));
        $token = (string) (config('twilio.auth_token') ?: config('services.twilio.token'));

        if ($sid === '' || $token === '') {
            throw new OutboundCallFailed('Twilio ist nicht eingerichtet: twilio.account_sid oder twilio.auth_token fehlt.');
        }

        return new Client($sid, $token);
    }
}
