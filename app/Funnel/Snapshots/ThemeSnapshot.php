<?php

declare(strict_types=1);

namespace App\Funnel\Snapshots;

use App\Constants\FunnelProgressStyle;
use App\Constants\FunnelThemeFont;

/**
 * Erscheinungsbild aus einem veroeffentlichten Snapshot (FB-017).
 *
 * Die Werte kommen so, wie sie beim Veroeffentlichen galten - eine spaetere
 * Aenderung am Theme wirkt sich erst auf die naechste Fassung aus. Unbekannte
 * Schrift- oder Fortschrittswerte fallen auf die Vorgabe zurueck, statt die
 * Auslieferung scheitern zu lassen: ein Snapshot aus einer aelteren Fassung
 * darf die oeffentliche Strecke nicht lahmlegen.
 */
class ThemeSnapshot
{
    public function __construct(
        public readonly string $primaryColor,
        public readonly string $secondaryColor,
        public readonly string $backgroundColor,
        public readonly string $textColor,
        public readonly FunnelThemeFont $font,
        public readonly string $fontFamily,
        public readonly ?string $logoPath,
        public readonly FunnelProgressStyle $progressStyle,
        public readonly int $borderRadius,
        public readonly string $buttonNextLabel,
        public readonly string $buttonBackLabel,
        public readonly string $buttonSubmitLabel,
    ) {}

    /**
     * @param  array<string, mixed>  $theme
     */
    public static function fromArray(array $theme): self
    {
        $font = FunnelThemeFont::tryFrom((string) ($theme['font'] ?? '')) ?? FunnelThemeFont::SYSTEM;

        return new self(
            primaryColor: (string) ($theme['primary_color'] ?? '#2563eb'),
            secondaryColor: (string) ($theme['secondary_color'] ?? '#64748b'),
            backgroundColor: (string) ($theme['background_color'] ?? '#ffffff'),
            textColor: (string) ($theme['text_color'] ?? '#0f172a'),
            font: $font,
            // Die font-family steht im Snapshot, damit die Runtime sie nicht aus
            // dem Enum ableiten muss; fehlt sie, liefert das Enum sie nach.
            fontFamily: (string) ($theme['font_family'] ?? $font->fontFamily()),
            logoPath: isset($theme['logo_path']) ? (string) $theme['logo_path'] : null,
            progressStyle: FunnelProgressStyle::tryFrom((string) ($theme['progress_style'] ?? ''))
                ?? FunnelProgressStyle::BAR,
            borderRadius: (int) ($theme['border_radius'] ?? 8),
            buttonNextLabel: (string) ($theme['button_next_label'] ?? ''),
            buttonBackLabel: (string) ($theme['button_back_label'] ?? ''),
            buttonSubmitLabel: (string) ($theme['button_submit_label'] ?? ''),
        );
    }

    /**
     * CSS-Custom-Properties fuer die oeffentliche Strecke.
     *
     * @return array<string, string>
     */
    public function cssVariables(): array
    {
        return [
            '--funnel-primary' => $this->primaryColor,
            '--funnel-secondary' => $this->secondaryColor,
            '--funnel-background' => $this->backgroundColor,
            '--funnel-text' => $this->textColor,
            '--funnel-font' => $this->fontFamily,
            '--funnel-radius' => $this->borderRadius.'px',
        ];
    }
}
