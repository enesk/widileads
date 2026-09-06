<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Berechtigungen eines Tenant-API-Tokens (FB-006).
 *
 * Ein Token traegt genau die Abilities, die beim Anlegen ausgewaehlt wurden.
 * Die Routen unter /api/v1 pruefen sie ueber die Sanctum-Middleware "ability".
 */
enum TenantApiAbility: string
{
    case FUNNELS_READ = 'funnels:read';

    case FUNNELS_WRITE = 'funnels:write';

    case LEADS_READ = 'leads:read';

    case WEBHOOKS_MANAGE = 'webhooks:manage';

    public function label(): string
    {
        return match ($this) {
            self::FUNNELS_READ => __('funnel.api_token.ability.funnels_read'),
            self::FUNNELS_WRITE => __('funnel.api_token.ability.funnels_write'),
            self::LEADS_READ => __('funnel.api_token.ability.leads_read'),
            self::WEBHOOKS_MANAGE => __('funnel.api_token.ability.webhooks_manage'),
        };
    }

    /**
     * @return array<string, string> Wert => Label, fuer Auswahlfelder.
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
