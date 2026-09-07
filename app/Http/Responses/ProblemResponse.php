<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Fehlerantwort nach RFC 9457 (FB-030b).
 *
 * `type` ist der stabile, maschinenlesbare Fehlerschluessel -- `title` und
 * `detail` sind fuer Menschen und duerfen sich aendern, ohne dass das ein Bruch
 * der API waere. Die Schluessel stehen in docs/openapi.yaml und sind dort Teil
 * des Vertrags.
 *
 * Abgrenzung zu App\Exceptions\ApiProblem (FB-030d): Der uebersetzt geworfene
 * AUSNAHMEN in dieses Format. Hier entstehen Problem-Antworten, die ein
 * Controller bewusst zurueckgibt, weil der Fall kein Fehler im technischen
 * Sinn ist -- ein Funnel mit Leads etwa ist ein gueltiger Zustand, nur eben
 * keiner, in dem geloescht werden darf. Beide erzeugen dasselbe Format.
 */
class ProblemResponse
{
    public const TYPE_UNAUTHENTICATED = '/problems/unauthenticated';

    public const TYPE_INSUFFICIENT_ABILITY = '/problems/insufficient-ability';

    public const TYPE_NOT_FOUND = '/problems/not-found';

    public const TYPE_FUNNEL_HAS_LEADS = '/problems/funnel-has-leads';

    public const TYPE_VALIDATION_FAILED = '/problems/validation-failed';

    public const TYPE_RATE_LIMIT_EXCEEDED = '/problems/rate-limit-exceeded';

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function make(
        string $type,
        string $title,
        int $status,
        ?string $detail = null,
        array $errors = [],
    ): JsonResponse {
        $payload = array_filter([
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ], static fn (mixed $value): bool => $value !== null);

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }
}
