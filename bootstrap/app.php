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
use App\Http\Middleware\VerifyTwilioSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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

        // FB-084: Die Signaturpruefung muss VOR SubstituteBindings stehen. Sonst
        // loest ein unsignierter Aufruf erst die Route-Modellbindung aus und
        // beantwortet einen unbekannten {attempt} mit 404 statt mit 403 -- die
        // Adresse wuerde verraten, welche Versuchs-IDs es gibt.
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            VerifyTwilioSignature::class,
        );

        // Die Twilio-Rueckrufe kommen von aussen und tragen kein CSRF-Token.
        // Sie sind allein durch die Signatur geschuetzt.
        $middleware->validateCsrfTokens(except: [
            'api/twilio/*',
        ]);

        // Die vertrauten Proxys werden NICHT hier gesetzt, sondern in
        // AppServiceProvider::boot(). Diese Closure laeuft, waehrend der
        // HTTP-Kernel aufgeloest wird -- also bevor die .env geladen ist.
        // env('TRUSTED_PROXIES') lieferte hier im Web null, waehrend es in der
        // Konsole den richtigen Wert hatte: Die Einstellung war jahrelang
        // scheinbar vorhanden und im Web wirkungslos.

        $middleware->alias([
            'sitemapped' => Sitemapped::class,
            'tenant.type' => EnsureTenantType::class,
            'marketplace.access' => EnsureMarketplaceAccess::class,
            'tenant.from-token' => ResolveTenantFromToken::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'twilio.signature' => VerifyTwilioSignature::class,
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
