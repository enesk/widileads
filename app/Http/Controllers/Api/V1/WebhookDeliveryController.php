<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Constants\WebhookDeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WebhookDeliveryResource;
use App\Models\Funnel;
use App\Models\FunnelWebhook;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Zustellprotokoll eines Webhooks (FB-030e).
 *
 * Fuer die Fehlersuche, wenn ein Empfaenger Ereignisse vermisst: Haben wir
 * zugestellt, was kam zurueck, wie oft wurde es versucht.
 */
class WebhookDeliveryController extends Controller
{
    public function index(Funnel $funnel, FunnelWebhook $webhook): AnonymousResourceCollection
    {
        $query = $webhook->deliveries();

        if (request()->filled('status')) {
            $status = WebhookDeliveryStatus::tryFrom((string) request()->string('status'));

            $query->where('status', $status?->value);
        }

        return WebhookDeliveryResource::collection(
            $query->paginate((int) request()->integer('per_page', 25))
        );
    }
}
