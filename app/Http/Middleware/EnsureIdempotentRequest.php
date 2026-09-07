<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ProblemResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wiederholte POST-Aufrufe mit demselben Schluessel (FB-030f).
 *
 * Ein Client, dessen Verbindung nach dem Absenden abbricht, weiss nicht, ob die
 * Aktion ausgefuehrt wurde. Ohne Idempotenz bleibt ihm nur, es erneut zu
 * versuchen -- und im Zweifel einen zweiten Funnel anzulegen. Mit dem Schluessel
 * bekommt er beim zweiten Versuch dieselbe Antwort wie beim ersten, ohne dass
 * etwas ein zweites Mal passiert.
 *
 * Unterscheidet sich der Rumpf bei gleichem Schluessel, ist es kein
 * Wiederholungsversuch, sondern ein anderer Aufruf unter fremdem Namen -- das
 * endet mit 409, statt eine falsche Antwort zurueckzugeben.
 */
class EnsureIdempotentRequest
{
    public const HEADER = 'Idempotency-Key';

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        if (! $request->isMethod('POST') || ! is_string($key) || trim($key) === '') {
            return $next($request);
        }

        // Der Schluessel gilt je Token: Zwei Workspaces duerfen denselben
        // Schluessel verwenden, ohne sich gegenseitig Antworten zu liefern.
        $cacheKey = 'idempotency:'.$this->tokenFingerprint($request).':'.sha1($key);
        $fingerprint = sha1($request->getContent());

        /** @var array{fingerprint: string, status: int, body: string}|null $stored */
        $stored = Cache::get($cacheKey);

        if ($stored !== null) {
            if ($stored['fingerprint'] !== $fingerprint) {
                return ProblemResponse::make(
                    ProblemResponse::TYPE_IDEMPOTENCY_KEY_REUSED,
                    __('api.problems.idempotency_key_reused'),
                    Response::HTTP_CONFLICT,
                    __('api.errors.idempotency_key_reused'),
                );
            }

            return response($stored['body'], $stored['status'])
                ->header('Content-Type', 'application/json')
                ->header('Idempotency-Replayed', 'true');
        }

        $response = $next($request);

        // Nur erfolgreiche Aktionen werden gemerkt: Ein fehlgeschlagener Aufruf
        // darf wiederholt werden, sonst brennt ein Tippfehler den Schluessel.
        if ($response->getStatusCode() < 300) {
            Cache::put($cacheKey, [
                'fingerprint' => $fingerprint,
                'status' => $response->getStatusCode(),
                'body' => $response->getContent(),
            ], now()->addHours((int) config('funnel.api.idempotency_ttl_hours')));
        }

        return $response;
    }

    private function tokenFingerprint(Request $request): string
    {
        $token = $request->user()?->currentAccessToken();

        return sha1((string) ($token?->getKey() ?? $request->ip()));
    }
}
