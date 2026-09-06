<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\SessionEventType;
use Database\Factories\PublicSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Eine Sitzung auf der oeffentlichen Funnel-Strecke (FB-021).
 *
 * Haelt den Teilfortschritt: Antworten und aktueller Schritt ueberleben damit
 * einen Reload oder einen Netzabbruch. Die Sitzung gehoert zu der Funnel-Fassung,
 * die der Endkunde gesehen hat -- nicht zum Funnel als solchem.
 *
 * @property int $id
 * @property int $funnel_version_id
 * @property string $token
 * @property array<string, mixed>|null $answers
 * @property int|null $current_step
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $utm_term
 * @property string|null $utm_content
 * @property string|null $referrer
 * @property string|null $embed_origin
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property array<string, mixed>|null $spam_signals
 * @property Carbon $started_at
 * @property Carbon $last_activity_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $abandoned_at
 */
class PublicSession extends Model
{
    /** @use HasFactory<PublicSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'funnel_version_id',
        'token',
        'answers',
        'current_step',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'referrer',
        'embed_origin',
        'ip_hash',
        'user_agent',
        'spam_signals',
        'started_at',
        'last_activity_at',
        'completed_at',
        'abandoned_at',
    ];

    /**
     * @return BelongsTo<FunnelVersion, $this>
     */
    public function funnelVersion(): BelongsTo
    {
        return $this->belongsTo(FunnelVersion::class);
    }

    /**
     * @return HasMany<SessionEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SessionEvent::class, 'session_id')->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->completed_at === null && $this->abandoned_at === null;
    }

    /**
     * Tatsaechlich gegangener Weg -- aus dem Ereignisprotokoll, nicht aus einer
     * zweiten Liste. Ein Verlauf, eine Quelle.
     *
     * @return list<int>
     */
    public function visitedStepPositions(): array
    {
        return $this->events
            ->where('type', SessionEventType::STEP_VIEW)
            ->pluck('step_position')
            ->filter(static fn (?int $position): bool => $position !== null)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Offene Sitzungen, die seit einem Zeitpunkt nichts mehr getan haben.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeStale(Builder $query, Carbon $inactiveSince): void
    {
        $query->whereNull('completed_at')
            ->whereNull('abandoned_at')
            ->where('last_activity_at', '<', $inactiveSince);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'spam_signals' => 'array',
            'current_step' => 'integer',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'completed_at' => 'datetime',
            'abandoned_at' => 'datetime',
        ];
    }
}
