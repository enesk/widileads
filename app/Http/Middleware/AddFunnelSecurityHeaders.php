<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Funnel\Runtime\EmbedOriginPolicy;
use App\Models\Funnel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sicherheitskoepfe der oeffentlichen Funnel-Strecke (FB-043).
 *
 * Der wichtigste Teil ist `frame-ancestors`. Ein Funnel ist zum Einbetten
 * gedacht, und wer ihn einbetten darf, steht seit FB-025 je Funnel in einer
 * Allowlist. Bisher wurde diese Liste nur serverseitig durchgesetzt: Ein
 * Aufruf mit fremdem Origin-Kopf bekommt 403. Ein iFrame laedt die Seite aber
 * ohne Origin-Kopf -- die Allowlist griff dort also gar nicht, und jede fremde
 * Seite konnte die Strecke einrahmen. Genau das ist Clickjacking.
 *
 * `frame-ancestors` schliesst diese Luecke auf der Browserseite, aus derselben
 * Liste. Beide Wege treffen damit dieselbe Entscheidung.
 *
 * Die uebrigen Direktiven stehen in config/funnel.php. Dort steht auch, warum
 * `'unsafe-inline'` vorerst dabei ist.
 */
class AddFunnelSecurityHeaders
{
    public function __construct(private readonly EmbedOriginPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $directives = [
            (string) config('funnel.public.csp'),
            'frame-ancestors '.implode(' ', $this->frameAncestors($request)),
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', array_filter($directives)));

        // Die Strecke traegt Kontaktdaten in der Adresse nicht, aber der Token
        // ist ein Geheimnis: Er soll nicht als Referrer bei jedem angeklickten
        // Ziel landen.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /**
     * Wer die Strecke einrahmen darf: die eigene Herkunft und die
     * freigegebenen Einbettungsziele des Funnels.
     *
     * Ohne Eintraege bleibt es bei `'self'` -- dieselbe Antwort, die
     * EmbedOriginPolicy einem fremden Origin gibt.
     *
     * @return list<string>
     */
    private function frameAncestors(Request $request): array
    {
        $ancestors = ["'self'"];

        $funnel = $this->funnelOf($request);

        if ($funnel === null) {
            return $ancestors;
        }

        foreach ($funnel->origins as $origin) {
            $normalized = $this->policy->normalize($origin->origin);

            if ($normalized !== null && ! in_array($normalized, $ancestors, true)) {
                $ancestors[] = $normalized;
            }
        }

        return $ancestors;
    }

    /**
     * Der Funnel zum oeffentlichen Token der Adresse.
     *
     * Eine zusaetzliche Abfrage je Aufruf. Sie waere vermeidbar, indem die
     * Strecke den Funnel im Request hinterlegt -- das haette aber eine
     * Reihenfolgenabhaengigkeit zwischen Middleware und Livewire-Komponente
     * geschaffen, fuer eine Abfrage auf einem Index.
     */
    private function funnelOf(Request $request): ?Funnel
    {
        $token = $request->route('token');

        if (! is_string($token) || $token === '') {
            return null;
        }

        return Funnel::query()
            ->withoutGlobalScopes()
            ->with('origins')
            ->where('public_token', $token)
            ->first();
    }
}
