<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Stand einer Reklamation (FB-058).
 *
 * Ein Kaeufer beantragt, dass ein gekaufter Lead als `unerreichbar` oder
 * `ungueltig` gilt. Entschieden wird das nicht automatisch: Der Antrag geht in
 * eine Pruefliste, ein Mensch sieht ihn an. Eine Gutschrift, die sich selbst
 * bewilligt, waere eine Einladung.
 */
enum ComplaintStatus: string
{
    /** Eingegangen, noch nicht geprueft. */
    case PENDING = 'pending';

    /** Anerkannt: Zustandswechsel und Gutschrift sind erfolgt. */
    case APPROVED = 'approved';

    /** Abgelehnt: Der Lead bleibt verkauft. */
    case REJECTED = 'rejected';

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function label(): string
    {
        return __('marketplace.complaint.status.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
