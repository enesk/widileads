<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Lebenszyklus eines Funnels (FB-010).
 *
 * Nur ein veroeffentlichter Funnel ist oeffentlich erreichbar. Ein archivierter
 * Funnel bleibt erhalten -- bereits entstandene Leads behalten damit ihren
 * Ursprung -- wird aber nicht mehr ausgeliefert.
 */
enum FunnelStatus: string
{
    case DRAFT = 'draft';

    case PUBLISHED = 'published';

    case ARCHIVED = 'archived';

    public function label(): string
    {
        return __('funnel.status.'.$this->value);
    }

    /**
     * Wird dieser Funnel oeffentlich ausgeliefert?
     */
    public function isPubliclyAvailable(): bool
    {
        return $this === self::PUBLISHED;
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
}
