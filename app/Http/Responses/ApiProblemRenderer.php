<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Uebersetzt Ausnahmen der Management-API in Problem Details (FB-030b).
 *
 * Fremde und nicht vorhandene Ressourcen werden bei 404 bewusst gleich
 * beantwortet: Wer die Kennung eines fremden Workspaces raet, soll an der
 * Antwort nicht erkennen koennen, ob es sie gibt.
 */
class ApiProblemRenderer
{
    public static function render(Throwable $exception): ?JsonResponse
    {
        return match (true) {
            $exception instanceof ValidationException => ProblemResponse::make(
                ProblemResponse::TYPE_VALIDATION_FAILED,
                __('api.problems.validation_failed'),
                422,
                $exception->getMessage(),
                $exception->errors(),
            ),
            $exception instanceof AuthenticationException => ProblemResponse::make(
                ProblemResponse::TYPE_UNAUTHENTICATED,
                __('api.problems.unauthenticated'),
                401,
            ),
            $exception instanceof AuthorizationException,
            $exception instanceof AccessDeniedHttpException => ProblemResponse::make(
                ProblemResponse::TYPE_INSUFFICIENT_ABILITY,
                __('api.problems.forbidden'),
                403,
                $exception->getMessage() ?: null,
            ),
            $exception instanceof ModelNotFoundException,
            $exception instanceof NotFoundHttpException => ProblemResponse::make(
                ProblemResponse::TYPE_NOT_FOUND,
                __('api.problems.not_found'),
                404,
            ),
            $exception instanceof TooManyRequestsHttpException => ProblemResponse::make(
                ProblemResponse::TYPE_RATE_LIMIT_EXCEEDED,
                __('api.problems.rate_limit_exceeded'),
                429,
            ),
            $exception instanceof HttpExceptionInterface => ProblemResponse::make(
                self::typeForStatus($exception->getStatusCode()),
                __('api.problems.error'),
                $exception->getStatusCode(),
                $exception->getMessage() ?: null,
            ),
            default => null,
        };
    }

    private static function typeForStatus(int $status): string
    {
        return match ($status) {
            401 => ProblemResponse::TYPE_UNAUTHENTICATED,
            403 => ProblemResponse::TYPE_INSUFFICIENT_ABILITY,
            404 => ProblemResponse::TYPE_NOT_FOUND,
            429 => ProblemResponse::TYPE_RATE_LIMIT_EXCEEDED,
            default => 'about:blank',
        };
    }
}
