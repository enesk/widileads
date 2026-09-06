<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mandantenbezug fuer alle Funnel-Builder-Tabellen (FB-010).
 *
 * Solange ein Mandant im Kontext ist -- im Dashboard-Panel setzt Filament ihn
 * pro Request -- sieht jede Abfrage nur dessen Datensaetze, und neue Datensaetze
 * bekommen die tenant_id automatisch. Ohne Mandantenkontext (Plattform-Admin
 * im Admin-Panel, Konsole, Queue) greift der Scope bewusst nicht; solcher Code
 * muss selbst einschraenken.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $tenantId = self::currentTenantId();

            if ($tenantId === null) {
                return;
            }

            $query->where($query->getModel()->qualifyColumn('tenant_id'), $tenantId);
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') !== null) {
                return;
            }

            $model->setAttribute('tenant_id', self::currentTenantId());
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    private static function currentTenantId(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? (int) $tenant->getKey() : null;
    }
}
