<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\AuditAction;
use App\Exceptions\AuditLogIsImmutableException;
use App\Models\Builders\AuditLogQueryBuilder;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Unveraenderlicher Eintrag des Audit-Logs (FB-005).
 *
 * Eintraege werden ausschliesslich ueber App\Services\AuditLogger angelegt und
 * danach nie wieder angefasst: Aendern und Loeschen werfen eine
 * AuditLogIsImmutableException -- sowohl ueber das Model als auch ueber den
 * Query-Builder.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $user_id
 * @property AuditAction $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed>|null $payload
 * @property string|null $ip_hash
 * @property Carbon|null $created_at
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    /**
     * Ein Audit-Eintrag wird nie aktualisiert, deshalb gibt es keine Spalte
     * updated_at.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'payload',
        'ip_hash',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw AuditLogIsImmutableException::forUpdate();
    }

    public function delete(): bool
    {
        throw AuditLogIsImmutableException::forDeletion();
    }

    /**
     * @param  Builder  $query
     */
    public function newEloquentBuilder($query): AuditLogQueryBuilder
    {
        return new AuditLogQueryBuilder($query);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Faengt auch Wege ab, die update()/delete() umgehen, etwa save() auf
        // einem geladenen Eintrag oder Beziehungen mit Kaskade.
        static::updating(function (): void {
            throw AuditLogIsImmutableException::forUpdate();
        });

        static::deleting(function (): void {
            throw AuditLogIsImmutableException::forDeletion();
        });
    }
}
