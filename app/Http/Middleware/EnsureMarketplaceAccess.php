<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\TenantTypeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laesst eine Route nur fuer freigeschaltete Kaeufer-Mandanten zu (FB-050).
 *
 * Verwendung: ->middleware('marketplace.access'). Sie traegt denselben Namen
 * wie das gleichnamige Gate und beantwortet dieselbe Frage ueber denselben
 * Dienst -- ein Endpunkt kann also nicht versehentlich die eine Pruefung
 * verwenden und die andere umgehen.
 *
 * Der Marktplatz selbst entsteht erst mit FB-053. Die Sperre haengt deshalb
 * bewusst hier und nicht an einer Seite: Wer die Marktplatz-Routen baut, haengt
 * diese Middleware davor und muss die Regel nicht kennen.
 */
class EnsureMarketplaceAccess
{
    public function __construct(private readonly TenantTypeService $tenantTypeService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantTypeService->currentTenant();

        if ($tenant === null) {
            abort(Response::HTTP_FORBIDDEN, __('funnel.tenant_type.no_tenant'));
        }

        if (! $tenant->isBuyer()) {
            abort(Response::HTTP_FORBIDDEN, __('funnel.tenant_type.forbidden', [
                'type' => $tenant->type->label(),
            ]));
        }

        if (! $this->tenantTypeService->canAccessMarketplace($tenant)) {
            abort(Response::HTTP_FORBIDDEN, __('funnel.buyer.not_approved'));
        }

        return $next($request);
    }
}
