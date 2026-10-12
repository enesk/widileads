<?php

declare(strict_types=1);

namespace App\Services\Twilio;

use App\Constants\PhoneVerificationChannel;
use Throwable;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

/**
 * Die echte Anbindung an Twilio Verify (LP-ONBOARD-004).
 *
 * Zugangsdaten und Service-SID kommen ausschliesslich aus der Konfiguration
 * (`services.twilio.*`). Fehlt eines davon, wird gar nicht erst gewaehlt --
 * eine Twilio-Fehlermeldung dazu kann im Portal niemand deuten.
 *
 * Der Verify-Service ist in der Twilio-Konsole getrennt von Nummern und
 * Anrufen angelegt und hat eine eigene SID ("VA..."). Dort stehen auch
 * Codelaenge, Laufzeit und der Absendername -- bewusst nicht bei uns, damit
 * eine Aenderung daran keinen Code braucht.
 */
class TwilioVerifyClient implements VerifyClient
{
    /**
     * Twilio meldet "kein offener Vorgang zu dieser Nummer" als 404. Das ist
     * fuer uns kein Fehler, sondern ein abgelaufener oder verbrauchter Code.
     */
    private const NOT_FOUND = 404;

    public function startVerification(string $phoneE164, PhoneVerificationChannel $channel): string
    {
        $service = $this->serviceSid();

        try {
            $verification = $this->client()
                ->verify
                ->v2
                ->services($service)
                ->verifications
                ->create($phoneE164, $channel->value, $this->options($channel));
        } catch (Throwable $exception) {
            throw new VerifyRequestFailed($exception->getMessage(), 0, $exception);
        }

        return (string) $verification->sid;
    }

    public function checkVerification(string $phoneE164, string $code): bool
    {
        $service = $this->serviceSid();

        try {
            $check = $this->client()
                ->verify
                ->v2
                ->services($service)
                ->verificationChecks
                ->create([
                    'to' => $phoneE164,
                    'code' => $code,
                ]);
        } catch (RestException $exception) {
            if ($exception->getStatusCode() === self::NOT_FOUND) {
                return false;
            }

            throw new VerifyRequestFailed($exception->getMessage(), 0, $exception);
        } catch (Throwable $exception) {
            throw new VerifyRequestFailed($exception->getMessage(), 0, $exception);
        }

        // `valid` ist die Zusicherung von Twilio, dass der Code zu dieser
        // Nummer gehoerte. Der Status allein genuegt nicht: Er steht auch auf
        // "pending", wenn noch Versuche offen sind.
        return (bool) $check->valid;
    }

    /**
     * Zusatzangaben an Twilio -- derzeit nur der Host fuer die WebOTP-Zeile.
     *
     * Chrome fuer Android bietet den Code aus einer SMS ausschliesslich ueber
     * die WebOTP-API an, und die verlangt als letzte Zeile der SMS
     * `@<host> #<code>` mit genau dem Host der aufgerufenen Seite
     * (Ticket #57). Den Wortlaut haelt das Template des Verify-Services in der
     * Twilio-Konsole; von hier kommt nur der Wert fuer dessen Platzhalter
     * `{{host}}`. Kennt das Template ihn nicht, lehnt Twilio den Aufruf ab --
     * deshalb haengt das an `services.twilio.verify_webotp` und ist aus,
     * solange das Template nicht steht.
     *
     * Beim Anruf-Ausweg bleibt es leer: Dort sagt eine Stimme den Code an, eine
     * SMS gibt es nicht.
     *
     * @return array<string, string>
     */
    private function options(PhoneVerificationChannel $channel): array
    {
        if ($channel !== PhoneVerificationChannel::SMS || ! config('services.twilio.verify_webotp')) {
            return [];
        }

        $host = (string) (config('services.twilio.verify_webotp_host')
            ?: parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($host === '') {
            return [];
        }

        return [
            'templateCustomSubstitutions' => json_encode(['host' => $host], JSON_THROW_ON_ERROR),
        ];
    }

    private function client(): Client
    {
        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.token');

        if ($sid === '' || $token === '') {
            throw new VerifyRequestFailed('Twilio ist nicht eingerichtet: services.twilio.sid oder .token fehlt.');
        }

        return new Client($sid, $token);
    }

    private function serviceSid(): string
    {
        $service = (string) config('services.twilio.verify_sid');

        if ($service === '') {
            throw new VerifyRequestFailed('Twilio Verify ist nicht eingerichtet: services.twilio.verify_sid fehlt.');
        }

        return $service;
    }
}
