<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    /**
     * Gibt den Tenant zurueck, zu dem das verwendete Token gehoert.
     *
     * Dient Integrationen dazu, ein Token zu pruefen und zu erfahren, welche
     * Abilities es traegt - ohne dafuer fachliche Endpunkte anzusprechen.
     */
    public function show(Request $request): TenantResource
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        return new TenantResource($tenant);
    }

    /**
     * Sonde fuer die Ability-Pruefung: erreichbar nur mit einem Token, das
     * leads:read traegt. Ersetzt keinen fachlichen Endpunkt - die kommen ab
     * FB-030 und tragen dieselbe Middleware.
     *
     * @return array{status: string}
     */
    public function ping(): array
    {
        return ['status' => 'ok'];
    }
}
