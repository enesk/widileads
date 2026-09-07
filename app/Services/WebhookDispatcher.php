<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\WebhookDeliveryStatus;
use App\Constants\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Funnel;
use App\Models\FunnelWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Str;

/**
 * Erzeugt Zustellungen fuer ein Ereignis (FB-030e).
 *
 * Je Webhook eine eigene Zustellung mit eigener Ereigniskennung: Wiederholungen
 * behalten ihre Kennung, damit der Empfaenger sie erkennt und nicht zweimal
 * verarbeitet.
 *
 * Die Nutzlast wird HIER gebaut und gespeichert, nicht erst beim Versand. Sonst
 * traege ein spaeter Versuch den Stand von heute statt den von damals -- ein
 * Ereignis beschreibt aber, was passiert ist, nicht was inzwischen gilt.
 */
class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $data  Nutzlast des Ereignisses
     */
    public function dispatch(Funnel $funnel, WebhookEvent $event, array $data): void
    {
        $webhooks = FunnelWebhook::query()
            ->where('funnel_id', $funnel->getKey())
            ->where('active', true)
            ->get()
            ->filter(fn (FunnelWebhook $webhook): bool => $webhook->listensTo($event));

        foreach ($webhooks as $webhook) {
            $delivery = WebhookDelivery::query()->create([
                'webhook_id' => $webhook->getKey(),
                'event_id' => (string) Str::uuid(),
                'event' => $event,
                'payload' => $this->envelope($funnel, $event, $data),
                'status' => WebhookDeliveryStatus::PENDING,
                'attempts' => 0,
                'next_attempt_at' => now(),
            ]);

            DeliverWebhook::dispatch($delivery->getKey());
        }
    }

    /**
     * Gemeinsamer Rahmen aller Ereignisse (Schema `WebhookEnvelope`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function envelope(Funnel $funnel, WebhookEvent $event, array $data): array
    {
        return [
            'id' => (string) Str::uuid(),
            'event' => $event->value,
            'occurred_at' => now()->toIso8601String(),
            'funnel' => [
                'public_token' => $funnel->public_token,
                'name' => $funnel->name,
            ],
            'data' => $data,
        ];
    }
}
