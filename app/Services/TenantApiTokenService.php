<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\AuditAction;
use App\Constants\TenantApiAbility;
use App\Exceptions\TenantApiTokenLimitReachedException;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Verwaltet die API-Tokens eines Tenants (FB-006).
 *
 * Tokens haengen am Tenant, nicht am Nutzer. Der Klartext des Tokens existiert
 * nur im Rueckgabewert von create() - gespeichert wird ausschliesslich der Hash.
 * Ablauf und Obergrenze kommen aus config/funnel.php.
 *
 * Anlegen und Widerrufen sind Pflichtereignisse des Audit-Logs (FB-005). Der
 * Klartext des Tokens wird dabei nie protokolliert - nur Bezeichnung, Abilities
 * und die Token-ID.
 */
class TenantApiTokenService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  list<string>  $abilities
     *
     * @throws TenantApiTokenLimitReachedException
     */
    public function create(Tenant $tenant, string $name, array $abilities): NewAccessToken
    {
        $limit = $this->maxTokensPerTenant();

        if ($limit > 0 && $tenant->tokens()->count() >= $limit) {
            throw new TenantApiTokenLimitReachedException($limit);
        }

        $abilities = $this->sanitizeAbilities($abilities);

        $token = $tenant->createToken($name, $abilities, $this->expiresAt());

        $this->auditLogger->log(
            AuditAction::API_TOKEN_CREATED,
            subject: $token->accessToken,
            payload: [
                'name' => $name,
                'abilities' => $abilities,
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            ],
            tenant: $tenant,
        );

        return $token;
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
        /** @var PersonalAccessToken|null $token */
        $token = $tenant->tokens()->whereKey($tokenId)->first();

        if ($token === null) {
            return false;
        }

        // Bezeichnung und Abilities vor dem Loeschen sichern, damit der
        // Audit-Eintrag den widerrufenen Zugang noch beschreiben kann.
        $payload = [
            'name' => $token->name,
            'abilities' => $token->abilities ?? [],
        ];

        if ($token->delete() !== true) {
            return false;
        }

        $this->auditLogger->log(
            AuditAction::API_TOKEN_DELETED,
            subject: $token,
            payload: $payload,
            tenant: $tenant,
        );

        return true;
    }

    public function maxTokensPerTenant(): int
    {
        return (int) config('funnel.api.max_tokens_per_tenant');
    }

    /**
     * Ablaufzeitpunkt neuer Tokens, oder null wenn sie unbefristet gelten.
     */
    private function expiresAt(): ?Carbon
    {
        $days = (int) config('funnel.api.token_expiration_days');

        return $days > 0 ? now()->addDays($days) : null;
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
