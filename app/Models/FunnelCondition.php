<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FunnelConditionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Verzweigungsregel eines Funnels (FB-010).
 *
 * Trifft die Regel zu, geht es mit `target_step_id` weiter; bei mehreren
 * Treffern gewinnt die hoechste `priority`. Ausgewertet wird sie erst vom
 * StepResolver in FB-012 -- hier steht ausschliesslich die Struktur.
 *
 * @property int $id
 * @property int $funnel_id
 * @property int $source_question_id
 * @property string $operator
 * @property array<array-key, mixed>|string|int|float|bool|null $value
 * @property int $target_step_id
 * @property int $priority
 */
class FunnelCondition extends Model
{
    /** @use HasFactory<FunnelConditionFactory> */
    use HasFactory;

    protected $fillable = [
        'funnel_id',
        'source_question_id',
        'operator',
        'value',
        'target_step_id',
        'priority',
    ];

    /**
     * @return BelongsTo<Funnel, $this>
     */
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    /**
     * Frage, deren Antwort geprueft wird.
     *
     * @return BelongsTo<FunnelQuestion, $this>
     */
    public function sourceQuestion(): BelongsTo
    {
        return $this->belongsTo(FunnelQuestion::class, 'source_question_id');
    }

    /**
     * Schritt, zu dem bei einem Treffer gesprungen wird.
     *
     * @return BelongsTo<FunnelStep, $this>
     */
    public function targetStep(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'target_step_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
            'priority' => 'integer',
        ];
    }
}
