<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt den Mandantenkontext der Portalseiten aus der UUID im Pfad (Portal Phase 1).
 *
 * Das Portal laeuft ausserhalb der Filament-Panels, braucht aber denselben
 * Kontext: Der Global Scope aus BelongsToTenant, TenantTypeService, die
 * Policies und ein gutes Dutzend Services lesen den aktiven Mandanten ueber
 * Filament::getTenant(). Diese Middleware legt ihn deshalb genauso ab wie
 * ResolveTenantFromToken es fuer die API tut -- setTenant() ist eine reine
 * Zuweisung im FilamentManager und verlangt kein Panel. So bleibt der
 * vorhandene Code unveraendert nutzbar, statt einen zweiten Weg zu bekommen,
 * der irgendwann auseinanderlaeuft.
 *
 * Reihenfolge: Die Middleware haengt in der Prioritaetsliste vor
 * SubstituteBindings (siehe bootstrap/app.php). Sonst wuerden mandantengebundene
 * Route-Modelle (etwa ein Kaufbeleg im Lead-Detail) ohne Mandantenkontext
 * gebunden -- ein fremder Datensatz wuerde gefunden statt in 404 zu enden.
 *
 * Fehlschlaege: unbekannte UUID = 404, fehlende Mitgliedschaft = 403. Die
 * Trennung ist Absicht; eine fremde, aber existierende Workspace-UUID darf
 * sich nicht von einer erfundenen unterscheiden lassen -- deshalb ist der
 * 403-Fall ohne Hinweis auf den Mandanten.
 */
class ResolvePortalTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $uuid = $request->route('tenant');

        $tenant = $uuid instanceof Tenant
            ? $uuid
            : Tenant::query()->where('uuid', (string) $uuid)->first();

        if (! $tenant instanceof Tenant) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if (! $user->canAccessTenant($tenant)) {
            abort(Response::HTTP_FORBIDDEN, __('funnel.tenant_type.no_tenant'));
        }

        Filament::setTenant($tenant, isQuiet: true);

        // Die Route traegt danach das Modell statt der UUID, damit Seiten und
        // Komponenten den Mandanten ohne zweite Abfrage bekommen.
        $request->route()->setParameter('tenant', $tenant);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
