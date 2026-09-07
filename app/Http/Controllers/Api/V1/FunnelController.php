<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FunnelWriteRequest;
use App\Http\Resources\Api\V1\FunnelResource;
use App\Http\Responses\ProblemResponse;
use App\Models\Funnel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Funnels der Management-API (FB-030b).
 *
 * Der Mandantenfilter kommt aus dem Trait BelongsToTenant: Die Middleware
 * tenant.from-token setzt den Tenant aus dem Sanctum-Token, der Global Scope
 * filtert jede Abfrage darauf. Ein fremder Funnel ist damit nicht nur
 * unzugaenglich, sondern nicht einmal sichtbar -- er fuehrt zu 404 wie ein
 * Funnel, den es gar nicht gibt.
 */
class FunnelController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $query = Funnel::query()->with('currentVersion');

        if (request()->filled('status')) {
            $query->where('status', request()->string('status'));
        }

        if (request()->filled('search')) {
            $search = '%'.request()->string('search').'%';
            $query->where(fn ($query) => $query->where('name', 'like', $search)->orWhere('slug', 'like', $search));
        }

        $sort = (string) request()->string('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, ['name', 'created_at', 'updated_at'], true)) {
            $column = 'created_at';
            $direction = 'desc';
        }

        return FunnelResource::collection(
            $query->orderBy($column, $direction)->paginate((int) request()->integer('per_page', 25))
        );
    }

    public function store(FunnelWriteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $name = (string) $data['name'];

        $funnel = Funnel::query()->create([
            ...$data,
            'slug' => $data['slug'] ?? Str::slug($name),
        ]);

        return FunnelResource::make($funnel)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Funnel $funnel): FunnelResource
    {
        return FunnelResource::make($funnel->loadMissing('currentVersion'));
    }

    public function update(FunnelWriteRequest $request, Funnel $funnel): FunnelResource
    {
        $funnel->update($request->validated());

        return FunnelResource::make($funnel->fresh()?->loadMissing('currentVersion') ?? $funnel);
    }

    /**
     * Ein Funnel mit Leads wird nicht geloescht: Die Leads verweisen auf ihn und
     * auf seine Fassungen, und ohne ihn waere nicht mehr nachvollziehbar, woraus
     * sie entstanden sind. Solche Funnels werden archiviert (FB-030c).
     */
    public function destroy(Funnel $funnel): JsonResponse
    {
        if ($this->hasLeads($funnel)) {
            return ProblemResponse::make(
                ProblemResponse::TYPE_FUNNEL_HAS_LEADS,
                __('api.problems.funnel_has_leads'),
                Response::HTTP_CONFLICT,
                __('api.errors.funnel_has_leads'),
            );
        }

        $funnel->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hasLeads(Funnel $funnel): bool
    {
        return DB::table('leads')->where('funnel_id', $funnel->getKey())->exists();
    }
}
