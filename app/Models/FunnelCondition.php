<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\ConditionOperator;
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
 * `source_question_id` sagt, WAS geprueft wird, `evaluate_at_step_position`
 * sagt, WANN (FB-012a). Beides zu trennen ist noetig, damit sich eine Regel auf
 * eine frueher gegebene Antwort beziehen kann: "anderes Tier ueberspringt Rasse
 * und Groesse" wird erst zwei Schritte spaeter wirksam. Ohne eigenen Wert gilt
 * der Schritt der Ausgangsfrage.
 *
 * @property int $id
 * @property int $funnel_id
 * @property int $source_question_id
 * @property ConditionOperator $operator
 * @property array<array-key, mixed>|string|int|float|bool|null $value
 * @property int $target_step_id
 * @property int|null $evaluate_at_step_position
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
        'evaluate_at_step_position',
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
     * Schritt, an dem diese Regel greift -- ohne eigenen Wert der Schritt der
     * Ausgangsfrage.
     */
    public function evaluationStepPosition(): ?int
    {
        return $this->evaluate_at_step_position ?? $this->sourceQuestion?->step?->position;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operator' => ConditionOperator::class,
            'value' => 'array',
            'evaluate_at_step_position' => 'integer',
            'priority' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Vorgabe: Die Regel greift dort, wo ihre Ausgangsfrage steht. Damit
        // verhalten sich bestehende Funnels unveraendert; wer eine spaetere
        // Auswertung will, setzt die Position ausdruecklich.
        static::saving(function (self $condition): void {
            if ($condition->evaluate_at_step_position !== null || $condition->source_question_id === null) {
                return;
            }

            $condition->evaluate_at_step_position = FunnelStep::query()
                ->whereKey(
                    FunnelQuestion::query()->whereKey($condition->source_question_id)->value('step_id')
                )
                ->value('position');
        });
    }
}
