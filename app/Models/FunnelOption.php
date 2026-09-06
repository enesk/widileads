<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FunnelOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Antwortoption einer Auswahlfrage (FB-010).
 *
 * `score` traegt die Punkte, mit denen die Option in die Auswertung eingeht
 * (Scoring in FB-013); ohne Punktwert bleibt sie neutral.
 *
 * @property int $id
 * @property int $question_id
 * @property int $position
 * @property string $label
 * @property string $value
 * @property int|null $score
 * @property string|null $image_path
 */
class FunnelOption extends Model
{
    /** @use HasFactory<FunnelOptionFactory> */
    use HasFactory;

    protected $fillable = [
        'question_id',
        'position',
        'label',
        'value',
        'score',
        'image_path',
    ];

    /**
     * @return BelongsTo<FunnelQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(FunnelQuestion::class, 'question_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'score' => 'integer',
        ];
    }
}
