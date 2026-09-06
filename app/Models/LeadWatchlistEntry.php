<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ein vorgemerkter Lead im Marktplatz (FB-053).
 *
 * Eine private Notiz des Kaeufers. Sie reserviert nichts und aendert am Lead
 * nichts -- ein vorgemerkter Lead kann jederzeit von jemand anderem gekauft
 * werden.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $lead_id
 * @property Carbon|null $created_at
 */
class LeadWatchlistEntry extends Model
{
    use BelongsToTenant;

    protected $table = 'lead_watchlist';

    /**
     * Eine Vormerkung wird nie aktualisiert -- sie wird gesetzt oder entfernt.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'lead_id',
    ];

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
