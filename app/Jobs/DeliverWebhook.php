<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Constants\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Services\WebhookSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Stellt ein Ereignis zu und protokolliert den Versuch (FB-030e).
 *
 * Wiederholt wird mit steigendem Abstand aus config/funnel.php. Der Job plant
 * seinen naechsten Versuch selbst, statt Laravels Retry zu nutzen: So steht der
 * Zustand nach jedem Versuch in webhook_deliveries -- wenn ein Empfaenger
 * Ereignisse vermisst, ist genau das die Frage, die beantwortet werden muss.
 *
 * Ein Fehler beim Empfaenger ist kein Fehler bei uns: Der Job wirft nicht,
 * sondern hinterlaesst einen lesbaren Zustand.
 */
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(WebhookSigner $signer): void
    {
        $delivery = WebhookDelivery::query()->with('webhook')->find($this->deliveryId);

        if ($delivery === null || $delivery->status === WebhookDeliveryStatus::DELIVERED) {
            return;
        }

        $webhook = $delivery->webhook;

        if ($webhook === null || ! $webhook->active) {
            return;
        }

        $payload = (string) json_encode($delivery->payload);
        $timestamp = now()->getTimestamp();
        $attempts = $delivery->attempts + 1;

        try {
            $response = Http::withHeaders(
                $signer->headersFor($webhook->secret, $payload, $delivery->event->value, $timestamp)
            )
                ->timeout((int) config('funnel.webhooks.timeout_seconds'))
                ->withBody($payload, 'application/json')
                ->post($webhook->url);

            $responseCode = $response->status();
            $successful = $response->successful();
        } catch (Throwable) {
            // Zeitueberschreitung oder nicht erreichbarer Host: kein Code.
            $responseCode = null;
            $successful = false;
        }

        if ($successful) {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::DELIVERED,
                'response_code' => $responseCode,
                'attempts' => $attempts,
                'last_attempt_at' => now(),
                'next_attempt_at' => null,
            ])->save();

            $webhook->forceFill(['last_delivery_at' => now()])->save();

            return;
        }

        $this->scheduleRetryOrGiveUp($delivery, $attempts, $responseCode);
    }

    private function scheduleRetryOrGiveUp(WebhookDelivery $delivery, int $attempts, ?int $responseCode): void
    {
        $maxAttempts = (int) config('funnel.webhooks.max_attempts');
        /** @var list<int> $delays */
        $delays = array_values((array) config('funnel.webhooks.retry_delays'));

        if ($attempts >= $maxAttempts) {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::EXHAUSTED,
                'response_code' => $responseCode,
                'attempts' => $attempts,
                'last_attempt_at' => now(),
                'next_attempt_at' => null,
            ])->save();

            return;
        }

        $delay = (int) ($delays[$attempts - 1] ?? end($delays));

        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::PENDING,
            'response_code' => $responseCode,
            'attempts' => $attempts,
            'last_attempt_at' => now(),
            'next_attempt_at' => now()->addSeconds($delay),
        ])->save();

        self::dispatch($delivery->getKey())->delay(now()->addSeconds($delay));
    }
}
