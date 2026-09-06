<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\FunnelFieldKey;
use App\Constants\QuestionType;
use Database\Factories\FunnelQuestionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eine Frage innerhalb eines Funnel-Schritts (FB-010).
 *
 * Der Feldschluessel ist der Name, unter dem die Antwort spaeter am Lead
 * haengt. Er wird beim Speichern vereinheitlicht und auf die reservierten
 * Kontakt-Feldschluessel abgebildet ("E-Mail" -> "e_mail" -> "email") und ist je
 * Funnel eindeutig -- dafuer traegt die Frage neben step_id auch funnel_id.
 *
 * @property int $id
 * @property int $funnel_id
 * @property int $step_id
 * @property int $position
 * @property QuestionType $type
 * @property string $field_key
 * @property string $label
 * @property string|null $help_text
 * @property bool $required
 * @property array<string, mixed>|null $validation
 * @property array<string, mixed>|null $meta
 */
class FunnelQuestion extends Model
{
    /** @use HasFactory<FunnelQuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'funnel_id',
        'step_id',
        'position',
        'type',
        'field_key',
        'label',
        'help_text',
        'required',
        'validation',
        'meta',
    ];

    /**
     * @return BelongsTo<FunnelStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'step_id');
    }

    /**
     * @return BelongsTo<Funnel, $this>
     */
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    /**
     * @return HasMany<FunnelOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(FunnelOption::class, 'question_id')->orderBy('position');
    }

    /**
     * Traegt diese Frage einen plattformweit reservierten Kontakt-Feldschluessel?
     */
    public function hasReservedFieldKey(): bool
    {
        return FunnelFieldKey::isReserved($this->field_key);
    }

    /**
     * Vereinheitlicht den Feldschluessel bei jedem Setzen und loest gebraeuchliche
     * Schreibweisen auf den reservierten Schluessel auf.
     *
     * @return Attribute<string, string>
     */
    protected function fieldKey(): Attribute
    {
        return Attribute::set(fn (string $value): string => FunnelFieldKey::resolve($value));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'position' => 'integer',
            'required' => 'boolean',
            'validation' => 'array',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Die funnel_id ergibt sich aus dem Schritt; sie steht nur an der Frage,
        // damit der Unique-Index ueber (funnel_id, field_key) moeglich ist.
        static::saving(function (self $question): void {
            if ($question->funnel_id !== null || $question->step_id === null) {
                return;
            }

            $question->funnel_id = (int) FunnelStep::query()
                ->whereKey($question->step_id)
                ->value('funnel_id');
        });
    }
}
