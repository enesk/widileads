<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\LeadState;
use App\Constants\TenancyPermissionConstants;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Baut die gefilterte Lead-Liste eines Betreibers (FB-034).
 *
 * Bewusst eine eigene Klasse und keine Methode in der Livewire-Komponente: Die
 * Filter sind Fachlogik, sie sollen ohne Oberflaeche pruefbar sein -- und die
 * Suche entscheidet mit, welche Daten jemand zu sehen bekommt.
 *
 * Gefiltert wird ausschliesslich in der Datenbank. Bei 50.000 Leads ist alles
 * andere unbrauchbar, deshalb stehen die Werte, nach denen gefiltert wird, als
 * Spalten an `leads` (Zustand, Punktzahl, Postleitzahl, Kontakt) und nicht nur
 * in den Rohantworten.
 */
class LeadListQuery
{
    public function __construct(private readonly TenantPermissionService $permissions) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Lead>
     */
    public function build(Tenant $tenant, User $viewer, array $filters = []): Builder
    {
        $query = Lead::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey());

        $this->applyFunnel($query, $filters['funnel_id'] ?? null);
        $this->applyState($query, $filters['lead_state'] ?? null);
        $this->applyPeriod($query, $filters['from'] ?? null, $filters['until'] ?? null);
        $this->applyPostalPrefix($query, $filters['postal_prefix'] ?? null);
        $this->applyScoreRange($query, $filters['score_min'] ?? null, $filters['score_max'] ?? null);
        $this->applySearch($query, $viewer, $tenant, $filters['search'] ?? null);

        return $query->latest('created_at')->latest('id');
    }

    /**
     * Leads, die zu lange im Marktplatz stehen, ohne gekauft zu werden.
     *
     * Sie sind das Warnsignal des Betreibers: Entweder stimmt der Preis nicht,
     * oder der Funnel liefert Leads, die niemand haben will.
     */
    public function isStale(Lead $lead): bool
    {
        if ($lead->lead_state !== LeadState::VERFUEGBAR) {
            return false;
        }

        return $lead->created_at?->lt($this->staleBefore()) === true;
    }

    public function staleBefore(): Carbon
    {
        return now()->subDays((int) config('funnel.lead.stale_after_days'));
    }

    /**
     * Darf dieser Benutzer ueber Kontaktdaten suchen -- oder nur ueber Namen?
     */
    public function maySearchContacts(Tenant $tenant, User $viewer): bool
    {
        return $this->permissions->tenantUserHasPermissionTo(
            $tenant,
            $viewer,
            TenancyPermissionConstants::PERMISSION_SEARCH_LEAD_CONTACTS,
        );
    }

    /**
     * @param  Builder<Lead>  $query
     */
    private function applyFunnel(Builder $query, mixed $funnelId): void
    {
        if (is_numeric($funnelId)) {
            $query->where('funnel_id', (int) $funnelId);
        }
    }

    /**
     * @param  Builder<Lead>  $query
     */
    private function applyState(Builder $query, mixed $state): void
    {
        if (is_string($state) && LeadState::tryFrom($state) !== null) {
            $query->where('lead_state', $state);
        }
    }

    /**
     * @param  Builder<Lead>  $query
     */
    private function applyPeriod(Builder $query, mixed $from, mixed $until): void
    {
        if (is_string($from) && $from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }

        if (is_string($until) && $until !== '') {
            $query->whereDate('created_at', '<=', $until);
        }
    }

    /**
     * @param  Builder<Lead>  $query
     */
    private function applyPostalPrefix(Builder $query, mixed $prefix): void
    {
        if (! is_string($prefix) || trim($prefix) === '') {
            return;
        }

        // Praefix von links: nur so kann der Index greifen.
        $query->where('postal_code', 'like', $this->escapeLike(trim($prefix)).'%');
    }

    /**
     * @param  Builder<Lead>  $query
     */
    private function applyScoreRange(Builder $query, mixed $min, mixed $max): void
    {
        if (is_numeric($min)) {
            $query->where('score', '>=', (int) $min);
        }

        if (is_numeric($max)) {
            $query->where('score', '<=', (int) $max);
        }
    }

    /**
     * Volltext -- der Umfang haengt an der Berechtigung.
     *
     * Verwalter suchen ueber Name, E-Mail und Telefon; alle anderen nur ueber
     * den Namen. Die Einschraenkung ist keine Maskierung, sondern verhindert,
     * dass sich jemand ohne Zugriff auf Kontaktdaten die Datenbank in kleinen
     * Schritten zusammensucht.
     *
     * @param  Builder<Lead>  $query
     */
    private function applySearch(Builder $query, User $viewer, Tenant $tenant, mixed $term): void
    {
        if (! is_string($term) || trim($term) === '') {
            return;
        }

        $needle = '%'.$this->escapeLike(trim($term)).'%';
        $searchesContacts = $this->maySearchContacts($tenant, $viewer);

        $query->where(function (Builder $query) use ($needle, $searchesContacts): void {
            $query->whereHas('answers', function ($answers) use ($needle): void {
                $answers->whereIn('field_key', ['vorname', 'nachname', 'name'])
                    ->where('value', 'like', $needle);
            });

            if ($searchesContacts) {
                $query->orWhere('email_normalized', 'like', $needle)
                    ->orWhere('phone_e164', 'like', $needle);
            }
        });
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
