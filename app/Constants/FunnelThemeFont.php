<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Zulaessige Schriften eines Funnel-Themes (FB-017).
 *
 * Bewusst eine geschlossene Liste statt eines freien Textfelds: Der Wert landet
 * im Snapshot und wird in der oeffentlichen Strecke unveraendert in eine
 * font-family gesetzt. Ein freier String waere damit ein Einfallstor und
 * ausserdem eine Quelle fuer Funnels, die beim Endkunden nicht darstellbar sind.
 */
enum FunnelThemeFont: string
{
    case SYSTEM = 'system';

    case INTER = 'inter';

    case ROBOTO = 'roboto';

    case OPEN_SANS = 'open_sans';

    public function label(): string
    {
        return __('builder.theme.font.'.$this->value);
    }

    /**
     * Die font-family, die in der oeffentlichen Strecke gesetzt wird.
     */
    public function fontFamily(): string
    {
        return match ($this) {
            self::SYSTEM => 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif',
            self::INTER => '"Inter", ui-sans-serif, system-ui, sans-serif',
            self::ROBOTO => '"Roboto", ui-sans-serif, system-ui, sans-serif',
            self::OPEN_SANS => '"Open Sans", ui-sans-serif, system-ui, sans-serif',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
