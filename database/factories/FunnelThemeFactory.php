<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\FunnelProgressStyle;
use App\Constants\FunnelThemeFont;
use App\Models\Funnel;
use App\Models\FunnelTheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FunnelTheme>
 */
class FunnelThemeFactory extends Factory
{
    protected $model = FunnelTheme::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funnel_id' => Funnel::factory(),
            'primary_color' => '#2563eb',
            'secondary_color' => '#64748b',
            'background_color' => '#ffffff',
            'text_color' => '#0f172a',
            'font' => FunnelThemeFont::SYSTEM,
            'progress_style' => FunnelProgressStyle::BAR,
            'border_radius' => 8,
        ];
    }
}
