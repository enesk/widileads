<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Wann ein Kaeufer von neuen passenden Leads erfaehrt (Portal Phase 1).
 */
enum BuyerNotifyInterval: string
{
    case IMMEDIATE = 'immediate';

    case DAILY = 'daily';

    case NONE = 'none';

    public function label(): string
    {
        return __('marketplace.profile.portal.notify.'.$this->value);
    }

    /**
     * @return array<string, string> Wert => Beschriftung, fuer Auswahlfelder.
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
