<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Typ eines Tenants im Funnel Builder.
 *
 * Ein Tenant ist entweder Betreiber (baut und veroeffentlicht Funnels) oder
 * Kaeufer (kauft die daraus entstehenden Leads ueber den Marktplatz). Beides
 * gleichzeitig ist fachlich ausgeschlossen.
 */
enum TenantType: string
{
    case OPERATOR = 'operator';

    case BUYER = 'buyer';

    public function label(): string
    {
        return match ($this) {
            self::OPERATOR => __('funnel.tenant_type.operator'),
            self::BUYER => __('funnel.tenant_type.buyer'),
        };
    }

    /**
     * Darf dieser Tenant-Typ Funnels verwalten?
     */
    public function canManageFunnels(): bool
    {
        return $this === self::OPERATOR;
    }

    /**
     * Darf dieser Tenant-Typ den Lead-Marktplatz nutzen?
     */
    public function canAccessMarketplace(): bool
    {
        return $this === self::BUYER;
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
}
