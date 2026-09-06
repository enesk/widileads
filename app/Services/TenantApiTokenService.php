<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\TenantApiAbility;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Verwaltet die API-Tokens eines Tenants (FB-006).
 *
 * Tokens haengen am Tenant, nicht am Nutzer. Der Klartext des Tokens existiert
 * nur im Rueckgabewert von create() - gespeichert wird ausschliesslich der Hash.
 */
class TenantApiTokenService
{
    /**
     * @param  list<string>  $abilities
     */
    public function create(Tenant $tenant, string $name, array $abilities): NewAccessToken
    {
        return $tenant->createToken($name, $this->sanitizeAbilities($abilities));
    }

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function tokensFor(Tenant $tenant): Collection
    {
        /** @var Collection<int, PersonalAccessToken> $tokens */
        $tokens = $tenant->tokens()->latest()->get();

        return $tokens;
    }

    /**
     * Widerruft ein Token. Gibt false zurueck, wenn es nicht zu diesem Tenant
     * gehoert - ein Tenant darf keine fremden Tokens loeschen.
     */
    public function revoke(Tenant $tenant, int $tokenId): bool
    {
        $deleted = $tenant->tokens()
            ->whereKey($tokenId)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Nur bekannte Abilities zulassen und Duplikate entfernen, damit kein
     * Token mit einer erfundenen Berechtigung entsteht.
     *
     * @param  list<string>  $abilities
     * @return list<string>
     */
    private function sanitizeAbilities(array $abilities): array
    {
        $allowed = TenantApiAbility::values();

        return array_values(array_unique(array_filter(
            $abilities,
            static fn (string $ability): bool => in_array($ability, $allowed, true),
        )));
    }
}
