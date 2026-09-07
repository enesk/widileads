<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Funnel\Runtime\EmbedOriginPolicy;
use App\Models\Funnel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laesst nur freigegebene Herkuenfte an die oeffentliche API (FB-025 fuer FB-026).
 *
 * Dieselbe Policy wie bei der eingebetteten Strecke -- die Entscheidung, wer
 * einen Funnel von aussen benutzen darf, liegt an genau einer Stelle. Ohne
 * Origin-Kopf ist es kein Browser-Aufruf von einer fremden Seite (etwa ein
 * Server-zu-Server-Aufruf), und der bleibt erlaubt.
 *
 * Durchgesetzt wird serverseitig mit 403, nicht ueber CORS-Koepfe: Ein
 * Access-Control-Header schuetzt nur den Browser eines Besuchers davor, eine
 * fremde Antwort zu lesen -- ein Aufruf ohne Browser ignoriert ihn vollstaendig.
 * Wer die Allowlist ueber Header durchsetzen wollte, haette also gar keinen
 * Schutz. Antwortet der Server dagegen mit 403, gibt es nichts zu lesen,
 * unabhaengig davon, was im Header steht.
 */
class EnsureAllowedFunnelOrigin
{
    public function __construct(private readonly EmbedOriginPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $funnel = $request->route('funnel');

        if (! $funnel instanceof Funnel) {
            return $next($request);
        }

        $origin = $request->headers->get('origin');

        if (! $this->policy->allows($funnel, $origin)) {
            $this->policy->recordRejection($funnel, $origin);

            abort(Response::HTTP_FORBIDDEN, __('runtime.errors.origin_not_allowed'));
        }

        return $next($request);
    }
}
