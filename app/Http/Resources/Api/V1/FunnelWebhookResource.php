<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\FunnelWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ein Webhook (FB-030e, Schema `FunnelWebhook` bzw. `FunnelWebhookWithSecret`).
 *
 * Das Secret erscheint ausschliesslich, wenn es gerade erzeugt wurde -- beim
 * Anlegen und beim Erneuern. Danach nie wieder: Wer es einmal verpasst hat,
 * erneuert es, statt es nachzuschlagen. Ein Secret, das jederzeit abrufbar ist,
 * ist bei jedem Lesezugriff auf die API mitgelesen.
 *
 * @mixin FunnelWebhook
 */
class FunnelWebhookResource extends JsonResource
{
    public function __construct(
        FunnelWebhook $webhook,
        private readonly ?string $plainSecret = null,
    ) {
        parent::__construct($webhook);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'events' => $this->events,
            'active' => $this->active,
            'last_delivery_at' => $this->last_delivery_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'secret' => $this->plainSecret,
        ];
    }
}
