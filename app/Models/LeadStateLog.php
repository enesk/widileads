<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Exceptions\LeadStateLogIsImmutableException;
use App\Models\Builders\LeadStateLogQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Unveraenderlicher Eintrag des Lead-Zustandsprotokolls (FB-030).
 *
 * Jeder Zustandswechsel eines Leads erzeugt genau einen Eintrag. Eintraege
 * entstehen ausschliesslich in App\Services\LeadStateService::transition() und
 * werden danach nie wieder angefasst: Aendern und Loeschen werfen eine
 * LeadStateLogIsImmutableException -- sowohl ueber das Model als auch ueber den
 * Query-Builder (gleiches Muster wie AuditLog, FB-005).
 *
 * @property int $id
 * @property int $lead_id
 * @property LeadState|null $from_state
 * @property LeadState $to_state
 * @property LeadTransitionReason $reason
 * @property int|null $actor_id
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 */
class LeadStateLog extends Model
{
    /**
     * Die Tabelle heisst bewusst im Singular: sie ist ein Protokoll, keine
     * Sammlung gleichrangiger Datensaetze (so auch im Datenmodell, Teil 2).
     */
    protected $table = 'lead_state_log';

    /**
     * Ein Protokolleintrag wird nie aktualisiert, deshalb gibt es keine Spalte
     * updated_at.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'lead_id',
        'from_state',
        'to_state',
        'reason',
        'actor_id',
        'meta',
    ];

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Benutzer, der den Wechsel ausgeloest hat. Null bei automatischen
     * Uebergaengen durch Jobs oder den Scheduler.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw LeadStateLogIsImmutableException::forUpdate();
    }

    public function delete(): bool
    {
        throw LeadStateLogIsImmutableException::forDeletion();
    }

    /**
     * @param  Builder  $query
     */
    public function newEloquentBuilder($query): LeadStateLogQueryBuilder
    {
        return new LeadStateLogQueryBuilder($query);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_state' => LeadState::class,
            'to_state' => LeadState::class,
            'reason' => LeadTransitionReason::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Faengt auch Wege ab, die update()/delete() umgehen, etwa save() auf
        // einem geladenen Eintrag oder Beziehungen mit Kaskade.
        static::updating(function (): void {
            throw LeadStateLogIsImmutableException::forUpdate();
        });

        static::deleting(function (): void {
            throw LeadStateLogIsImmutableException::forDeletion();
        });
    }
}
