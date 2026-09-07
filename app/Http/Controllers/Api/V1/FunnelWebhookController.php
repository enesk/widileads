<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FunnelWebhookWriteRequest;
use App\Http\Resources\Api\V1\FunnelWebhookResource;
use App\Models\Funnel;
use App\Models\FunnelWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Webhooks eines Funnels (FB-030e).
 */
class FunnelWebhookController extends Controller
{
    public function index(Funnel $funnel): AnonymousResourceCollection
    {
        return FunnelWebhookResource::collection($funnel->webhooks()->get());
    }

    public function store(FunnelWebhookWriteRequest $request, Funnel $funnel): JsonResponse
    {
        $secret = FunnelWebhook::newSecret();

        $webhook = $funnel->webhooks()->create([
            ...$request->safe()->only(['url', 'events', 'active']),
            'secret' => $secret,
        ]);

        // Einziger Zeitpunkt, an dem das Secret sichtbar ist.
        return FunnelWebhookResource::make($webhook, $secret)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Funnel $funnel, FunnelWebhook $webhook): FunnelWebhookResource
    {
        return FunnelWebhookResource::make($webhook);
    }

    public function update(
        FunnelWebhookWriteRequest $request,
        Funnel $funnel,
        FunnelWebhook $webhook,
    ): FunnelWebhookResource {
        $webhook->update($request->safe()->only(['url', 'events', 'active']));

        if (! $request->boolean('rotate_secret')) {
            return FunnelWebhookResource::make($webhook->fresh() ?? $webhook);
        }

        // Das alte Secret wird sofort ungueltig: Wer es erneuert, tut das in der
        // Regel, weil es abhandengekommen ist.
        $secret = FunnelWebhook::newSecret();
        $webhook->update(['secret' => $secret]);

        return FunnelWebhookResource::make($webhook->fresh() ?? $webhook, $secret);
    }

    public function destroy(Funnel $funnel, FunnelWebhook $webhook): JsonResponse
    {
        $webhook->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
