<?php

use App\Http\Middleware\BlockedUser;
use App\Http\Middleware\EnsureMarketplaceAccess;
use App\Http\Middleware\EnsureTenantType;
use App\Http\Middleware\ResolveTenantFromToken;
use App\Http\Middleware\Sitemapped;
use App\Http\Middleware\TrackCouponCode;
use App\Http\Middleware\TrackReferralCode;
use App\Http\Middleware\UpdateUserLastSeenAt;
use App\Http\Responses\ApiProblemRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
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

        // FB-030b: Der Mandantenkontext muss VOR dem Aufloesen der Route-Modelle
        // stehen. Sonst laeuft SubstituteBindings aus der api-Gruppe zuerst, der
        // Global Scope aus BelongsToTenant findet keinen Tenant -- und ein
        // {funnel:public_token} eines fremden Workspaces wird gebunden, statt in
        // 404 zu enden. In Tests faellt das nicht auf, weil ein vorheriger
        // Request den Tenant im Container hinterlaesst; in Produktion ist jeder
        // Request frisch.
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            ResolveTenantFromToken::class,
        );

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
        // FB-030b: Die Management-API antwortet nach RFC 9457. Ohne diese
        // Uebersetzung lieferte Laravel sein eigenes Fehlerformat, und die
        // Spezifikation waere an dieser Stelle unwahr.
        $exceptions->render(function (Throwable $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return ApiProblemRenderer::render($exception);
        });
    })->create();
