<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\FunnelProgressStyle;
use App\Constants\FunnelThemeFont;
use Database\Factories\FunnelThemeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Erscheinungsbild eines Funnels (FB-017).
 *
 * Genau ein Theme je Funnel. Die Werte landen ueber den SnapshotBuilder im
 * veroeffentlichten Snapshot - ein Theme, das nur hier steht, waere in der
 * oeffentlichen Strecke unsichtbar, weil die ausschliesslich Snapshots liest.
 *
 * @property int $id
 * @property int $funnel_id
 * @property string $primary_color
 * @property string $secondary_color
 * @property string $background_color
 * @property string $text_color
 * @property FunnelThemeFont $font
 * @property string|null $logo_path
 * @property FunnelProgressStyle $progress_style
 * @property string|null $button_next_label
 * @property string|null $button_back_label
 * @property string|null $button_submit_label
 * @property int $border_radius
 */
class FunnelTheme extends Model
{
    /** @use HasFactory<FunnelThemeFactory> */
    use HasFactory;

    protected $fillable = [
        'funnel_id',
        'primary_color',
        'secondary_color',
        'background_color',
        'text_color',
        'font',
        'logo_path',
        'progress_style',
        'button_next_label',
        'button_back_label',
        'button_submit_label',
        'border_radius',
    ];

    /**
     * @return BelongsTo<Funnel, $this>
     */
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    /**
     * Beschriftung der Weiter-Schaltflaeche, oder die Vorgabe.
     */
    public function nextLabel(): string
    {
        return $this->button_next_label ?: __('builder.theme.default_next');
    }

    public function backLabel(): string
    {
        return $this->button_back_label ?: __('builder.theme.default_back');
    }

    public function submitLabel(): string
    {
        return $this->button_submit_label ?: __('builder.theme.default_submit');
    }

    /**
     * Die Darstellung fuer den Snapshot.
     *
     * Die Button-Texte stehen hier bereits aufgeloest: Der Snapshot muss ohne
     * die Live-Tabellen und ohne die Sprachdateien des Betreibers lesbar sein,
     * deshalb keine leeren Felder und keine Uebersetzungsschluessel.
     *
     * @return array<string, mixed>
     */
    public function toSnapshotArray(): array
    {
        return [
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'background_color' => $this->background_color,
            'text_color' => $this->text_color,
            'font' => $this->font->value,
            'font_family' => $this->font->fontFamily(),
            'logo_path' => $this->logo_path,
            'progress_style' => $this->progress_style->value,
            'border_radius' => $this->border_radius,
            'button_next_label' => $this->nextLabel(),
            'button_back_label' => $this->backLabel(),
            'button_submit_label' => $this->submitLabel(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'font' => FunnelThemeFont::class,
            'progress_style' => FunnelProgressStyle::class,
            'border_radius' => 'integer',
        ];
    }
}
