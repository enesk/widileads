<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Constants\TenantType;
use App\Services\TenantTypeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laesst eine Route nur fuer Tenants eines bestimmten Typs zu (FB-002).
 *
 * Verwendung: ->middleware('tenant.type:operator') bzw. 'tenant.type:buyer'.
 * Mehrere Typen werden mit Komma bzw. als weitere Parameter uebergeben.
 * Ohne aktiven Tenant oder bei falschem Typ: 403.
 */
class EnsureTenantType
{
    public function __construct(private readonly TenantTypeService $tenantTypeService) {}

    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $tenant = $this->tenantTypeService->currentTenant();

        if ($tenant === null) {
            abort(Response::HTTP_FORBIDDEN, __('funnel.tenant_type.no_tenant'));
        }

        $allowed = [];

        foreach ($types as $type) {
            foreach (explode(',', $type) as $singleType) {
                $allowed[] = TenantType::from(trim($singleType));
            }
        }

        if (! in_array($tenant->type, $allowed, true)) {
            abort(Response::HTTP_FORBIDDEN, __('funnel.tenant_type.forbidden', [
                'type' => $tenant->type->label(),
            ]));
        }

        return $next($request);
    }
}
