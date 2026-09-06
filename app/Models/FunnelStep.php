<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FunnelStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Schritt (Seite) eines Funnels (FB-010).
 *
 * @property int $id
 * @property int $funnel_id
 * @property int $position
 * @property string $title
 * @property string|null $description
 */
class FunnelStep extends Model
{
    /** @use HasFactory<FunnelStepFactory> */
    use HasFactory;

    protected $fillable = [
        'funnel_id',
        'position',
        'title',
        'description',
    ];

    /**
     * @return BelongsTo<Funnel, $this>
     */
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    /**
     * @return HasMany<FunnelQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(FunnelQuestion::class, 'step_id')->orderBy('position');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }
}
