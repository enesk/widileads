<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Fehlerformat der Management-API nach RFC 9457 (FB-030d).
 *
 * Die Spezifikation beschreibt jeden Fehler als `application/problem+json` mit
 * einem stabilen, maschinenlesbaren `type`. `title` und `detail` sind fuer
 * Menschen und duerfen sich aendern, ohne dass das ein Bruch der API waere --
 * deshalb steht der Fehlerschluessel im `type` und nicht im Text.
 *
 * Betroffen sind ausschliesslich Anfragen unter /api/v1: Das Dashboard und die
 * oeffentliche Strecke behalten ihre gewohnten Antworten.
 */
class ApiProblem
{
    public static function handles(Request $request): bool
    {
        return $request->is('api/v1') || $request->is('api/v1/*');
    }

    /**
     * Uebersetzt eine Ausnahme in eine Problem-Antwort.
     */
    public static function fromThrowable(Throwable $exception, Request $request): JsonResponse
    {
        if ($exception instanceof ValidationException) {
            return self::response(
                type: '/problems/validation-failed',
                title: __('api.problems.validation_failed.title'),
                status: 422,
                detail: __('api.problems.validation_failed.detail'),
                request: $request,
                extra: ['errors' => $exception->errors()],
            );
        }

        // Fehlende Anmeldung vor fehlender Berechtigung: Ohne Token kann
        // niemandem eine Berechtigung fehlen. Je nach Reihenfolge der Middleware
        // kommt hier eine AuthenticationException an oder ein 403 aus einer
        // nachgelagerten Pruefung -- fuer den Aufrufer ist beides dasselbe, und
        // ein 403 waere irrefuehrend.
        if ($request->user() === null && self::meansMissingCredentials($exception)) {
            return self::response(
                type: '/problems/unauthenticated',
                title: __('api.problems.unauthenticated.title'),
                status: 401,
                detail: __('api.problems.unauthenticated.detail'),
                request: $request,
            );
        }

        if ($exception instanceof AuthorizationException || $exception instanceof AccessDeniedHttpException) {
            return self::response(
                type: '/problems/insufficient-ability',
                title: __('api.problems.insufficient_ability.title'),
                status: 403,
                detail: $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : __('api.problems.insufficient_ability.detail'),
                request: $request,
            );
        }

        if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
            return self::response(
                type: '/problems/not-found',
                title: __('api.problems.not_found.title'),
                status: 404,
                detail: __('api.problems.not_found.detail'),
                request: $request,
            );
        }

        if ($exception instanceof TooManyRequestsHttpException) {
            return self::response(
                type: '/problems/rate-limit-exceeded',
                title: __('api.problems.rate_limit.title'),
                status: 429,
                detail: __('api.problems.rate_limit.detail'),
                request: $request,
            );
        }

        return self::response(
            type: 'about:blank',
            title: __('api.problems.unexpected.title'),
            status: $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500,
            // Bewusst ohne die Ausnahmemeldung: Sie kann Interna enthalten.
            detail: __('api.problems.unexpected.detail'),
            request: $request,
        );
    }

    /**
     * Steht diese Ausnahme fuer eine fehlende Anmeldung?
     */
    private static function meansMissingCredentials(Throwable $exception): bool
    {
        if ($exception instanceof AuthenticationException) {
            return true;
        }

        if ($exception instanceof AuthorizationException || $exception instanceof AccessDeniedHttpException) {
            return true;
        }

        return $exception instanceof HttpExceptionInterface
            && in_array($exception->getStatusCode(), [401, 403], true);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private static function response(
        string $type,
        string $title,
        int $status,
        string $detail,
        Request $request,
        array $extra = [],
    ): JsonResponse {
        return new JsonResponse(
            [
                'type' => $type,
                'title' => $title,
                'status' => $status,
                'detail' => $detail,
                'instance' => '/'.ltrim($request->path(), '/'),
                ...$extra,
            ],
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
