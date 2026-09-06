<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\FunnelVersionIsImmutableException;
use App\Funnel\Snapshots\FunnelSnapshot;
use Database\Factories\FunnelVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eine veroeffentlichte Fassung eines Funnels (FB-014).
 *
 * Der Snapshot ist der Vertrag mit dem Endkunden: Was er gesehen und
 * beantwortet hat, muss auch dann noch nachvollziehbar sein, wenn der Entwurf
 * laengst weitergebaut wurde. Deshalb ist eine Version nach dem Anlegen
 * unveraenderlich -- Aendern und Loeschen werfen.
 *
 * @property int $id
 * @property int $funnel_id
 * @property int $version
 * @property array<string, mixed> $snapshot
 * @property Carbon $published_at
 * @property int|null $published_by
 */
class FunnelVersion extends Model
{
    /** @use HasFactory<FunnelVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'funnel_id',
        'version',
        'snapshot',
        'published_at',
        'published_by',
    ];

    /**
     * @return BelongsTo<Funnel, $this>
     */
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Gelesener Snapshot -- die Form, mit der StepResolver, ScoreCalculator und
     * ResultResolver arbeiten.
     */
    public function toSnapshot(): FunnelSnapshot
    {
        return FunnelSnapshot::fromArray($this->snapshot);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw FunnelVersionIsImmutableException::forUpdate();
    }

    public function delete(): bool
    {
        throw FunnelVersionIsImmutableException::forDeletion();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw FunnelVersionIsImmutableException::forUpdate();
        });

        static::deleting(function (): void {
            throw FunnelVersionIsImmutableException::forDeletion();
        });
    }
}
