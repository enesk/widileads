<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FunnelVersionResource;
use App\Http\Resources\Api\V1\FunnelVersionWithSnapshotResource;
use App\Models\Funnel;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Versionshistorie eines Funnels (FB-030c).
 */
class FunnelVersionController extends Controller
{
    public function index(Funnel $funnel): AnonymousResourceCollection
    {
        return FunnelVersionResource::collection(
            $funnel->versions()->with('publisher')->paginate((int) request()->integer('per_page', 25))
        );
    }

    /**
     * Adressiert wird ueber die Versionsnummer, nicht ueber die Datenbank-ID:
     * Sie ist die Nummer, die der Aufrufer in der Historie sieht.
     */
    public function show(Funnel $funnel, int $version): FunnelVersionWithSnapshotResource
    {
        return FunnelVersionWithSnapshotResource::make(
            $funnel->versions()->with('publisher')->where('version', $version)->firstOrFail()
        );
    }
}
