<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt den Tenant-Kontext aus dem Sanctum-Token (FB-006).
 *
 * Tokens gehoeren einem Tenant. Nach auth:sanctum ist der authentifizierte
 * "Nutzer" also der Tenant selbst. Diese Middleware haelt ihn fest, damit
 * Controller, Services und Global Scopes denselben Tenant sehen wie im
 * Dashboard - und niemals einen anderen.
 */
class ResolveTenantFromToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->user();

        if (! $tenant instanceof Tenant) {
            abort(Response::HTTP_FORBIDDEN, __('funnel.api_token.no_tenant_token'));
        }

        Filament::setTenant($tenant, isQuiet: true);

        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
