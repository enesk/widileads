<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\SessionEventType;
use App\Exceptions\SessionEventIsImmutableException;
use Database\Factories\SessionEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ein Ereignis im Verlauf einer oeffentlichen Sitzung (FB-021).
 *
 * Append-only: Ein Verlauf, der sich nachtraeglich aendern laesst, taugt weder
 * als Abbruchstatistik noch als Nachweis. Aendern und Loeschen werfen deshalb.
 *
 * @property int $id
 * @property int $session_id
 * @property SessionEventType $type
 * @property int|null $step_position
 * @property Carbon $created_at
 */
class SessionEvent extends Model
{
    /** @use HasFactory<SessionEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'session_id',
        'type',
        'step_position',
    ];

    /**
     * @return BelongsTo<PublicSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PublicSession::class, 'session_id');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw SessionEventIsImmutableException::forUpdate();
    }

    public function delete(): bool
    {
        throw SessionEventIsImmutableException::forDeletion();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SessionEventType::class,
            'step_position' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw SessionEventIsImmutableException::forUpdate();
        });

        static::deleting(function (): void {
            throw SessionEventIsImmutableException::forDeletion();
        });
    }
}
