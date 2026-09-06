<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @mixin Tenant
 */
class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $token = $this->currentAccessToken();

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'type' => $this->type->value,
            'abilities' => $token instanceof PersonalAccessToken ? $token->abilities : [],
        ];
    }
}
