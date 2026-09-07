<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Eine Zustellung (FB-030e, Schema `WebhookDelivery`).
 *
 * Die Nutzlast bleibt aussen vor: Sie kann Kontaktdaten enthalten, und das
 * Protokoll dient der Fehlersuche -- dafuer reichen Ereignis, Code und Zeiten.
 *
 * @mixin WebhookDelivery
 */
class WebhookDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event->value,
            'status' => $this->status->value,
            'response_code' => $this->response_code,
            'attempts' => $this->attempts,
            'last_attempt_at' => $this->last_attempt_at?->toIso8601String(),
            'next_attempt_at' => $this->next_attempt_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
