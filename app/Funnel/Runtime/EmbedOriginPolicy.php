<?php

declare(strict_types=1);

namespace App\Funnel\Runtime;

use App\Models\Funnel;

/**
 * Entscheidet, ob ein Funnel auf einer fremden Seite eingebettet werden darf
 * (FB-024, durchgesetzt ab FB-025).
 *
 * Heute erlaubt die Policy jede Herkunft -- die Allowlist je Funnel
 * (`funnel_origins`) kommt mit FB-025 und wird ausschliesslich hier
 * ausgewertet. Die Stelle existiert bereits, damit die Runtime dann nicht mehr
 * angefasst werden muss und die Entscheidung an genau einem Ort liegt.
 */
class EmbedOriginPolicy
{
    public function allows(Funnel $funnel, ?string $origin): bool
    {
        // FB-025 prueft hier gegen funnel_origins plus die eigene Domain.
        return true;
    }

    /**
     * Normalisiert den vom Snippet mitgegebenen Origin auf "schema://host[:port]".
     * Alles andere ist keine Herkunft, sondern eine Behauptung.
     */
    public function normalize(?string $origin): ?string
    {
        if (! is_string($origin) || trim($origin) === '') {
            return null;
        }

        $parts = parse_url(trim($origin));

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $normalized = mb_strtolower($parts['scheme'].'://'.$parts['host']);

        return isset($parts['port']) ? $normalized.':'.$parts['port'] : $normalized;
    }
}
