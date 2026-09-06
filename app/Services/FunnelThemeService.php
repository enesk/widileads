<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Funnel;
use App\Models\FunnelTheme;

/**
 * Pflegt das Erscheinungsbild eines Funnels (FB-017).
 */
class FunnelThemeService
{
    /**
     * Das Theme des Funnels; ohne gepflegtes Theme wird eines mit den
     * Vorgabewerten angelegt, damit der Editor immer einen Datensatz hat.
     */
    public function forFunnel(Funnel $funnel): FunnelTheme
    {
        return $funnel->theme()->firstOrCreate([]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(FunnelTheme $theme, array $attributes): FunnelTheme
    {
        $theme->fill(array_intersect_key($attributes, array_flip([
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
        ])))->save();

        return $theme->refresh();
    }
}
