<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FunnelResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Ergebnis-Screen eines Funnels fuer einen Punktebereich (FB-010).
 *
 * Welcher Bereich zu einer Punktzahl passt und ob die Bereiche eines Funnels
 * lueckenlos und ueberschneidungsfrei sind, entscheidet der ResultResolver in
 * FB-013 -- hier steht ausschliesslich die Struktur.
 *
 * @property int $id
 * @property int $funnel_id
 * @property int $min_score
 * @property int $max_score
 * @property string $title
 * @property string|null $body
 * @property string|null $cta_label
 * @property string|null $cta_url
 * @property bool $show_contact_form
 */
class FunnelResult extends Model
{
    /** @use HasFactory<FunnelResultFactory> */
    use HasFactory;

    protected $fillable = [
        'funnel_id',
        'min_score',
        'max_score',
        'title',
        'body',
        'cta_label',
        'cta_url',
        'show_contact_form',
    ];

    /**
     * @return BelongsTo<Funnel, $this>
     */
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_score' => 'integer',
            'max_score' => 'integer',
            'show_contact_form' => 'boolean',
        ];
    }
}
