<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Constants\LeadState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListLeadsRequest;
use App\Http\Resources\Api\V1\LeadResource;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Leads lesen (FB-030d).
 *
 * Der Mandantenbezug kommt aus dem Token: "tenant.from-token" setzt den
 * Workspace, jede Abfrage schraenkt darauf ein. Ein Token erreicht damit
 * niemals Daten eines anderen Workspaces -- auch nicht mit einer geratenen
 * Kennung.
 *
 * Die Maskierung entscheidet ausschliesslich `Lead::contactFor()` in der
 * Resource. Hier gibt es dazu keine zweite Logik und keinen Parameter.
 */
class LeadController extends Controller
{
    public function index(ListLeadsRequest $request): AnonymousResourceCollection
    {
        $tenant = $this->tenant($request);

        $leads = $this->baseQuery($tenant);

        $this->applyFunnel($leads, $tenant, $request->query('funnel'));
        $this->applyStates($leads, (array) $request->query('state', []));
        $this->applyPeriod($leads, $request->query('created_from'), $request->query('created_until'));
        $this->applyPostalPrefix($leads, $request->query('postal_code_prefix'));
        $this->applyScoreRange($leads, $request->query('score_min'), $request->query('score_max'));
        $this->applyStale($leads, $request->boolean('stale'));
        $this->applySort($leads, (string) $request->query('sort', '-created_at'));

        return LeadResource::collection(
            $leads->paginate($request->perPage())->appends($request->query()),
        );
    }

    public function show(Request $request, string $lead): LeadResource
    {
        $found = $this->baseQuery($this->tenant($request))
            ->where('uuid', $lead)
            ->first();

        if ($found === null) {
            // Bewusst 404 und nicht 403: Ob es diesen Lead ueberhaupt gibt,
            // geht ein fremdes Token nichts an.
            throw new NotFoundHttpException;
        }

        return new LeadResource($found);
    }

    /**
     * @return Builder<Lead>
     */
    private function baseQuery(Tenant $tenant): Builder
    {
        return Lead::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->with(['answers', 'funnel', 'funnelVersion.publisher', 'stateLog']);
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->user();

        if (! $tenant instanceof Tenant) {
            throw new NotFoundHttpException;
        }

        return $tenant;
    }

    /**
     * @param  Builder<Lead>  $leads
     */
    private function applyFunnel(Builder $leads, Tenant $tenant, mixed $publicToken): void
    {
        if (! is_string($publicToken) || $publicToken === '') {
            return;
        }

        // Adressiert wird ueber den oeffentlichen Token, nie ueber die ID.
        $funnelId = Funnel::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('public_token', $publicToken)
            ->value('id');

        // Ein unbekannter Token liefert eine leere Seite, keinen Fehler: Die
        // Anfrage ist gueltig, sie trifft nur nichts.
        $leads->where('funnel_id', $funnelId ?? 0);
    }

    /**
     * @param  Builder<Lead>  $leads
     * @param  array<int, mixed>  $states
     */
    private function applyStates(Builder $leads, array $states): void
    {
        $values = array_values(array_filter(
            array_map(static fn (mixed $state): ?string => is_string($state) ? $state : null, $states),
            static fn (?string $state): bool => $state !== null && LeadState::tryFrom($state) !== null,
        ));

        if ($values !== []) {
            $leads->whereIn('lead_state', $values);
        }
    }

    /**
     * @param  Builder<Lead>  $leads
     */
    private function applyPeriod(Builder $leads, mixed $from, mixed $until): void
    {
        if (is_string($from) && $from !== '') {
            $leads->where('created_at', '>=', $from);
        }

        if (is_string($until) && $until !== '') {
            $leads->where('created_at', '<=', $until);
        }
    }

    /**
     * @param  Builder<Lead>  $leads
     */
    private function applyPostalPrefix(Builder $leads, mixed $prefix): void
    {
        if (! is_string($prefix) || $prefix === '') {
            return;
        }

        // Gefiltert wird serverseitig auf dem Klartext -- auch dann, wenn die
        // Postleitzahl im Ergebnis maskiert erscheint.
        $leads->where('postal_code', 'like', $prefix.'%');
    }

    /**
     * @param  Builder<Lead>  $leads
     */
    private function applyScoreRange(Builder $leads, mixed $min, mixed $max): void
    {
        if (is_numeric($min)) {
            $leads->where('score', '>=', (int) $min);
        }

        if (is_numeric($max)) {
            $leads->where('score', '<=', (int) $max);
        }
    }

    /**
     * @param  Builder<Lead>  $leads
     */
    private function applyStale(Builder $leads, bool $onlyStale): void
    {
        if (! $onlyStale) {
            return;
        }

        $leads->where('lead_state', LeadState::VERFUEGBAR->value)
            ->where('created_at', '<', now()->subDays((int) config('funnel.lead.stale_after_days')));
    }

    /**
     * @param  Builder<Lead>  $leads
     */
    private function applySort(Builder $leads, string $sort): void
    {
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        if (! in_array($column, ['created_at', 'score'], true)) {
            $column = 'created_at';
            $descending = true;
        }

        $leads->orderBy($column, $descending ? 'desc' : 'asc')->orderBy('id', 'desc');
    }
}
