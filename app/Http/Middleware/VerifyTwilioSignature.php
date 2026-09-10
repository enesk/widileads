<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

/**
 * Prueft die Signatur eines Twilio-Rueckrufs (FB-080).
 *
 * Ueber die Rueckrufe kommt herein, ob eine Rufnummer als Rufnummernanzeige
 * erscheinen darf und wie lange ein Gespraech gedauert hat. Die Adressen sind
 * oeffentlich erreichbar und tragen kein Geheimnis -- ohne Signaturpruefung
 * koennte jeder mit einem einzigen POST eine fremde Nummer freischalten oder
 * einen Gespraechsverlauf faelschen.
 *
 * Geprueft wird gegen den Auth Token des Twilio-Kontos ueber die volle URL
 * inklusive Query-String und alle Formularfelder des Rumpfs, genau wie Twilio
 * signiert. Fehlt der Token, wird jeder Aufruf abgewiesen: Ein nicht
 * eingerichtetes Konto darf nicht bedeuten, dass die Pruefung entfaellt.
 *
 * Die Middleware laeuft laut Prioritaetenliste vor SubstituteBindings -- ein
 * unsignierter Aufruf soll nicht erst eine Route-Modellbindung ausloesen.
 */
class VerifyTwilioSignature
{
    public const HEADER = 'X-Twilio-Signature';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->authToken();
        $signature = (string) $request->header(self::HEADER, '');

        if ($token === '' || $signature === '') {
            abort(Response::HTTP_FORBIDDEN);
        }

        $isValid = (new RequestValidator($token))->validate(
            $signature,
            $this->signedUrl($request),
            $this->signedParameters($request),
        );

        if (! $isValid) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /**
     * Kanonisch ist `twilio.auth_token` (FB-082). Der mitgelieferte
     * SaaSykit-Block `services.twilio.token` bleibt als Rueckfall bestehen,
     * weil das Admin-Panel den Token dorthin schreibt.
     */
    private function authToken(): string
    {
        $token = (string) config('twilio.auth_token');

        return $token !== '' ? $token : (string) config('services.twilio.token');
    }

    /**
     * Die URL, wie Twilio sie angerufen hat -- samt Query-String, denn der geht
     * in die Signatur ein. Hinter dem Reverse Proxy steht das Schema nur in den
     * weitergereichten Kopfzeilen: signiert wurde https, empfangen wuerde sonst
     * http und die Pruefung schluege immer fehl (siehe TrustProxies).
     */
    private function signedUrl(Request $request): string
    {
        return $request->fullUrl();
    }

    /**
     * Twilio signiert die Formularfelder des Rumpfs. Bei einem JSON-Rumpf gibt
     * es keine, dann zaehlt allein die URL.
     *
     * @return array<string, mixed>
     */
    private function signedParameters(Request $request): array
    {
        if ($request->isJson()) {
            return [];
        }

        return $request->request->all();
    }
}
