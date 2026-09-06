<?php

declare(strict_types=1);

namespace App\Funnel\Runtime;

use App\Services\IpHasher;
use Illuminate\Http\Request;

/**
 * Liest die Herkunft einer Sitzung aus der Anfrage (FB-022).
 *
 * Erfasst werden Kampagnenparameter, Referrer, der Origin der einbettenden
 * Seite und ein gekuerzter User-Agent. Die IP-Adresse verlaesst diesen Dienst
 * ausschliesslich als gesalzener SHA-256-Hash ueber den IpHasher -- dasselbe
 * Verfahren wie im Audit-Log, damit dieselbe Adresse ueberall denselben Hash
 * ergibt.
 */
class OriginCollector
{
    /**
     * Kampagnenparameter, die uebernommen werden.
     */
    private const UTM_PARAMETERS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    public function __construct(private readonly IpHasher $ipHasher) {}

    /**
     * @return array<string, string|null>
     */
    public function collect(Request $request): array
    {
        $origin = [];

        foreach (self::UTM_PARAMETERS as $parameter) {
            $origin[$parameter] = $this->trimmed($request->query($parameter));
        }

        $origin['referrer'] = $this->trimmed($request->headers->get('referer'));
        $origin['embed_origin'] = $this->trimmed($request->headers->get('origin'));
        $origin['user_agent'] = $this->trimmed(
            $request->userAgent(),
            (int) config('funnel.public.user_agent_max_length'),
        );

        // Ab hier existiert die Roh-IP nirgends mehr: weder in einer Spalte noch
        // in einer Variablen, die weitergereicht wird.
        $origin['ip_hash'] = $this->ipHasher->hash($request->ip());

        return $origin;
    }

    private function trimmed(mixed $value, ?int $maxLength = null): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $maxLength ??= (int) config('funnel.public.origin_max_length');

        return mb_substr($value, 0, $maxLength);
    }
}
