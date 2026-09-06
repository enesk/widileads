<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Darstellung des Fortschritts in der oeffentlichen Strecke (FB-017).
 */
enum FunnelProgressStyle: string
{
    /** Durchgehender Balken mit prozentualem Fortschritt. */
    case BAR = 'bar';

    /** Nummerierte Schritte, der aktuelle hervorgehoben. */
    case STEPS = 'steps';

    /** Keine Fortschrittsanzeige. */
    case NONE = 'none';

    public function label(): string
    {
        return __('builder.theme.progress.'.$this->value);
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
