<?php

use App\Exceptions\ApiProblem;
use App\Http\Middleware\BlockedUser;
use App\Http\Middleware\EnsureMarketplaceAccess;
use App\Http\Middleware\EnsureTenantType;
use App\Http\Middleware\ResolveTenantFromToken;
use App\Http\Middleware\Sitemapped;
use App\Http\Middleware\TrackCouponCode;
use App\Http\Middleware\TrackReferralCode;
use App\Http\Middleware\UpdateUserLastSeenAt;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('web', [
            BlockedUser::class,
            UpdateUserLastSeenAt::class,
            TrackReferralCode::class,
            TrackCouponCode::class,
        ]);

        $middleware->alias([
            'sitemapped' => Sitemapped::class,
            'tenant.type' => EnsureTenantType::class,
            'marketplace.access' => EnsureMarketplaceAccess::class,
            'tenant.from-token' => ResolveTenantFromToken::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // FB-030d: Die Management-API antwortet nach RFC 9457, wie in
        // docs/openapi.yaml beschrieben. Alles ausserhalb von /api/v1 behaelt
        // die gewohnten Antworten.
        $exceptions->render(function (Throwable $exception, Request $request) {
            return ApiProblem::handles($request)
                ? ApiProblem::fromThrowable($exception, $request)
                : null;
        });
    })->create();
